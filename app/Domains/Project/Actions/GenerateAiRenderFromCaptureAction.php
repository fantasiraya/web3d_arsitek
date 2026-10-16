<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateAiRenderFromCaptureAction
{
    public function __construct(
        protected ?SystemSettingRepository $settingsRepo = null
    ) {
        $this->settingsRepo = $settingsRepo ?? app(SystemSettingRepository::class);
    }

    public function execute(
        User $user,
        Project $project,
        ProjectCapture $capture,
        array $options = [],
        ?UploadedFile $uploadedReferenceImage = null
    ): ProjectCapture {
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat melakukan generate render AI.');
        }

        if ($capture->project_id !== $project->id) {
            abort(422, 'Capture tidak cocok dengan proyek.');
        }

        // Tentukan provider & model yang aktif
        $provider = strtolower((string) ($this->getSetting('image_render_provider') ?: config('services.image_render.provider') ?: 'pollinations'));
        $model = (string) ($this->getSetting('image_render_model') ?: config('services.image_render.model') ?: 'flux');
        $apiKey = (string) ($this->getSetting('image_render_api_key') ?: config('services.image_render.api_key'));

        // Tentukan prompt yang digunakan
        $userPrompt = trim((string) ($options['prompt'] ?? ''));
        if (empty($userPrompt)) {
            $userPrompt = $capture->prompt;
        }

        if (empty($userPrompt)) {
            $roomName = $capture->room_name ?: 'Modern Architectural Space';
            $mood = $options['mood'] ?? 'natural bright daylight';
            $userPrompt = "Masterpiece architectural photograph of {$roomName}, modern high-end architectural design, {$mood}, 100% strict adherence to the reference geometry, realistic physical textures, clean wood and concrete materials, natural soft illumination, cinematic depth of field, architectural digest 8k magazine quality.";
        }

        $aspectRatio = $options['aspect_ratio'] ?? '16:9';

        // Dapatkan URL gambar referensi 3D (dari file yang diunggah langsung atau file di storage)
        $referenceImageUrl = $this->getPublicImageUrl($capture, $uploadedReferenceImage);

        // Eksekusi render sesuai provider dengan mengirimkan gambar referensi
        $imageData = match ($provider) {
            'replicate' => $this->renderWithReplicate($apiKey ?: config('services.image_render.replicate.api_token'), $userPrompt, $aspectRatio, $model, $referenceImageUrl),
            'gemini'    => $this->callGeminiImagen($this->getSetting('gemini_api_key', config('services.vision_ai.providers.gemini.api_key') ?: config('services.gemini.api_key')), $userPrompt, $aspectRatio),
            default     => $this->renderWithPollinations($userPrompt, $aspectRatio, $model, $referenceImageUrl),
        };

        // Hapus file AI render lama jika ada
        if ($capture->ai_rendered_path && Storage::disk('public')->exists($capture->ai_rendered_path)) {
            Storage::disk('public')->delete($capture->ai_rendered_path);
        }

        // Simpan file hasil generate
        $filename = 'ai_render_' . Str::uuid() . '.jpg';
        $path = "projects/{$project->id}/captures/ai/{$filename}";
        Storage::disk('public')->put($path, $imageData);

        // Update model capture
        $capture->update([
            'ai_rendered_path' => $path,
            'prompt'           => $userPrompt,
        ]);

        return $capture->fresh();
    }

    /**
     * Dapatkan URL gambar yang dapat diakses oleh layanan AI online sebagai referensi geometri
     */
    protected function getPublicImageUrl(ProjectCapture $capture, ?UploadedFile $uploadedFile = null): ?string
    {
        $fileContents = null;
        $mime = 'image/jpeg';
        $ext = 'jpg';

        // Prioritaskan file yang dikirim langsung dari browser
        if ($uploadedFile && $uploadedFile->isValid()) {
            $fileContents = file_get_contents($uploadedFile->getRealPath());
            $mime = $uploadedFile->getClientMimeType() ?: 'image/jpeg';
            $ext = $uploadedFile->getClientOriginalExtension() ?: 'jpg';
        } elseif ($capture->file_path && Storage::disk('public')->exists($capture->file_path)) {
            $fileContents = Storage::disk('public')->get($capture->file_path);
            $mime = Storage::disk('public')->mimeType($capture->file_path) ?: 'image/jpeg';
            $ext = pathinfo($capture->file_path, PATHINFO_EXTENSION) ?: 'jpg';
        }

        if (empty($fileContents)) {
            return null;
        }

        // Jika URL sudah publik di domain online (bukan localhost/127.0.0.1)
        $rawUrl = $capture->url;
        if (!empty($rawUrl) && filter_var($rawUrl, FILTER_VALIDATE_URL)) {
            $host = parse_url($rawUrl, PHP_URL_HOST);
            if ($host && !in_array($host, ['localhost', '127.0.0.1', '::1']) && !str_ends_with($host, '.local') && !str_ends_with($host, '.test')) {
                return $rawUrl;
            }
        }

        // Jika berada di localhost / file lokal, kirim file ke temporary host agar AI bisa membacanya
        try {
            $response = Http::timeout(25)
                ->attach('file', $fileContents, "capture_ref.{$ext}", ['Content-Type' => $mime])
                ->post('https://tmpfiles.org/api/v1/upload');

            if ($response->successful()) {
                $json = $response->json();
                $pageUrl = $json['data']['url'] ?? null;
                if ($pageUrl) {
                    // Ubah tmpfiles.org/123/name.jpg menjadi tmpfiles.org/dl/123/name.jpg untuk direct download URL
                    return str_replace('tmpfiles.org/', 'tmpfiles.org/dl/', $pageUrl);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TmpFiles bridge error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Render via Pollinations.ai (Mengirimkan gambar referensi via parameter ?image=)
     */
    protected function renderWithPollinations(string $prompt, string $aspectRatio, string $model = 'flux', ?string $referenceImageUrl = null): string
    {
        [$width, $height] = match ($aspectRatio) {
            '4:3'   => [1024, 768],
            '1:1'   => [1024, 1024],
            default => [1280, 720], // 16:9
        };

        $seed = random_int(1000, 9999999);
        $encodedPrompt = rawurlencode($prompt);
        $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&model={$model}&nologo=true&seed={$seed}";

        // Sertakan gambar referensi jika tersedia
        if (!empty($referenceImageUrl)) {
            $url .= "&image=" . rawurlencode($referenceImageUrl);
        }

        $response = Http::timeout(90)->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Pollinations API error (HTTP {$response->status()}): " . substr($response->body(), 0, 150));
        }

        $body = $response->body();
        if (empty($body) || strlen($body) < 1000) {
            throw new RuntimeException("Respon dari generator render Pollinations.ai tidak valid.");
        }

        return $body;
    }

    /**
     * Render via Replicate (Flux / SDXL dengan dukungan input image)
     */
    protected function renderWithReplicate(?string $apiKey, string $prompt, string $aspectRatio, string $model, ?string $referenceImageUrl = null): string
    {
        if (empty($apiKey)) {
            throw new RuntimeException("REPLICATE_API_TOKEN belum disetel di .env untuk provider Replicate.");
        }

        $modelName = $model ?: 'black-forest-labs/flux-schnell';

        $inputPayload = [
            'prompt'        => $prompt,
            'aspect_ratio'  => $aspectRatio,
            'output_format' => 'jpg',
        ];

        if (!empty($referenceImageUrl)) {
            $inputPayload['image'] = $referenceImageUrl;
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post("https://api.replicate.com/v1/models/{$modelName}/predictions", [
                'input' => $inputPayload,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Replicate API error: " . ($response->json('detail') ?? $response->body()));
        }

        $prediction = $response->json();
        $status = $prediction['status'] ?? 'starting';
        $id = $prediction['id'] ?? null;

        // Polling hasil sampai status selesai
        $maxAttempts = 30;
        while (in_array($status, ['starting', 'processing']) && $maxAttempts > 0) {
            sleep(2);
            $poll = Http::withToken($apiKey)->get("https://api.replicate.com/v1/predictions/{$id}");
            if ($poll->successful()) {
                $prediction = $poll->json();
                $status = $prediction['status'] ?? 'failed';
            }
            $maxAttempts--;
        }

        if ($status === 'succeeded' && !empty($prediction['output'])) {
            $imageUrl = is_array($prediction['output']) ? $prediction['output'][0] : $prediction['output'];
            $imageContent = Http::timeout(45)->get($imageUrl)->body();
            if (!empty($imageContent)) {
                return $imageContent;
            }
        }

        throw new RuntimeException("Replicate render gagal atau timeout. Status: {$status}");
    }

    /**
     * Render via Google Gemini Imagen API
     */
    protected function callGeminiImagen(?string $apiKey, string $prompt, string $aspectRatio = '16:9'): string
    {
        if (empty($apiKey)) {
            throw new RuntimeException('API Key Gemini belum disetel. Pastikan GEMINI_API_KEY terpasang di .env.');
        }

        $models = ['imagen-3.0-generate-002', 'imagen-3.0-fast-generate-001'];
        $lastException = null;

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict?key={$apiKey}";

            try {
                $response = Http::timeout(60)->post($url, [
                    'instances' => [
                        [
                            'prompt' => $prompt,
                        ],
                    ],
                    'parameters' => [
                        'sampleCount' => 1,
                        'aspectRatio' => $aspectRatio,
                        'outputMimeType' => 'image/jpeg',
                    ],
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $base64 = $json['predictions'][0]['bytesBase64Encoded'] ?? null;
                    if ($base64) {
                        $binary = base64_decode($base64);
                        if ($binary !== false) {
                            return $binary;
                        }
                    }
                }

                $errorMsg = $response->json('error.message') ?? $response->body();
                $lastException = new RuntimeException("Gagal me-render dengan {$model}: {$errorMsg}");
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?: new RuntimeException('Gagal menghubungi API Gemini Imagen.');
    }

    protected function getSetting(string $key, mixed $default = null): mixed
    {
        try {
            $val = $this->settingsRepo?->get($key);
            if (!empty($val)) {
                return $val;
            }
        } catch (\Throwable) {
            // ignore
        }
        return $default;
    }
}
