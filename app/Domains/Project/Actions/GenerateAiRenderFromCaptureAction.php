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
        ?UploadedFile $uploadedReferenceImage = null,
        ?UploadedFile $uploadedDepthMap = null
    ): ProjectCapture {
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat melakukan generate render AI.');
        }

        if ($capture->project_id !== $project->id) {
            abort(422, 'Capture tidak cocok dengan proyek.');
        }

        // Resolusi konfigurasi AI langsung dari .env / database
        $dbProvider = $this->getSetting('image_render_provider');
        $provider = strtolower(trim((string) ($dbProvider ?: config('services.image_render.provider') ?: env('IMAGE_RENDER_PROVIDER'))));

        $dbModel = $this->getSetting('image_render_model');
        $model = trim((string) ($dbModel ?: config('services.image_render.model') ?: env('IMAGE_RENDER_MODEL')));

        if (empty($provider)) {
            throw new RuntimeException("IMAGE_RENDER_PROVIDER di file .env belum disetel atau kosong. Silakan tentukan provider pada .env (pilihan: pollinations, gemini, atau replicate).");
        }

        if (empty($model)) {
            throw new RuntimeException("IMAGE_RENDER_MODEL di file .env belum disetel atau kosong. Silakan tentukan model pada .env (contoh: 'flux' untuk pollinations, atau 'gemini-3.1-flash-image' untuk gemini).");
        }

        $geminiApiKey = (string) ($this->getSetting('gemini_api_key', config('services.vision_ai.providers.gemini.api_key') ?: config('services.gemini.api_key')));
        $replicateToken = (string) ($this->getSetting('replicate_api_token', env('REPLICATE_API_TOKEN')));

        // 1. Tentukan prompt yang digunakan (prioritaskan prompt dari modal / vision AI capture)
        $userPrompt = trim((string) ($options['prompt'] ?? ''));
        if (empty($userPrompt)) {
            $userPrompt = $capture->prompt;
        }

        if (empty($userPrompt)) {
            $roomName = $capture->room_name ?: 'Ruangan Arsitektur';
            $mood = $options['mood'] ?? 'natural bright daylight';
            $userPrompt = "Masterpiece architectural photograph of {$roomName}, modern high-end architectural design, {$mood}, 100% strict adherence to the reference geometry, realistic physical textures, clean wood and concrete materials, natural soft illumination, cinematic depth of field, architectural digest 8k magazine quality.";
        }

        $aspectRatio = $options['aspect_ratio'] ?? '16:9';

        // 2. Ekstrak file biner Gambar Referensi (RGB)
        $refBinary = $this->getImageBinary($capture, $uploadedReferenceImage);
        if (empty($refBinary)) {
            throw new RuntimeException('File gambar referensi 3D tidak ditemukan pada server.');
        }

        // 3. Ekstrak / Buat Depth Map (Peta Kedalaman Kontrol Geometri)
        $depthMapBinary = $this->resolveDepthMapBinary($capture, $uploadedDepthMap, $refBinary);
        if ($depthMapBinary) {
            $depthPath = "projects/{$project->id}/captures/depth/depth_{$capture->id}.png";
            Storage::disk('public')->put($depthPath, $depthMapBinary);
            $capture->depth_map_path = $depthPath;
            $capture->saveQuietly();
        }

        // 4. Resolusi Public URL untuk Gambar Referensi dan Depth Map (agar dapat diakses model AI online)
        $referenceImageUrl = $this->getPublicUrlForBinary(
            $refBinary,
            "ref_{$capture->id}.jpg",
            'image/jpeg',
            $capture->url
        );

        $depthMapUrl = $depthMapBinary ? $this->getPublicUrlForBinary(
            $depthMapBinary,
            "depth_{$capture->id}.png",
            'image/png',
            $capture->depth_map_url
        ) : null;

        // 5. Eksekusi render AI:
        // Agar geometri ruangan dan sudut kamera 100% presisi dan sama persis dengan snapshot 3D,
        // kita menggunakan engine image-guided (Flux / ControlNet) yang menerima gambar referensi dan depth map.
        $imageData = null;
        $geminiError = null;

        // 1. Jika provider GEMINI dipilih di .env
        if ($provider === 'gemini') {
            try {
                $imageData = $this->callGeminiImagen($geminiApiKey, $userPrompt, $aspectRatio, $model, $refBinary, $depthMapBinary);
            } catch (\Throwable $e) {
                $geminiError = $e->getMessage();
                Log::warning("Gemini Image Render [{$model}] gagal: {$geminiError}.");
                throw new RuntimeException("Gagal me-render dengan Gemini [{$model}]: {$geminiError}. Catatan: API image generation Gemini memerlukan akun berbayar Google AI Studio (Free Tier kuotanya 0). Untuk render gratis langsung tanpa kartu kredit, set di .env: IMAGE_RENDER_PROVIDER=pollinations dan IMAGE_RENDER_MODEL=flux.");
            }
        }
        // 2. Jika provider REPLICATE dipilih di .env
        elseif ($provider === 'replicate') {
            try {
                $imageData = $this->renderWithReplicate(
                    $replicateToken,
                    $userPrompt,
                    $aspectRatio,
                    $model ?: 'black-forest-labs/flux-schnell',
                    $referenceImageUrl,
                    $depthMapUrl
                );
            } catch (\Throwable $e) {
                throw new RuntimeException("Gagal me-render dengan Replicate [{$model}]: " . $e->getMessage());
            }
        }
        // 3. Jika provider POLLINATIONS dipilih di .env
        elseif ($provider === 'pollinations') {
            try {
                $imageData = $this->renderWithPollinations(
                    $userPrompt,
                    $aspectRatio,
                    $model ?: 'flux',
                    $referenceImageUrl,
                    $depthMapUrl
                );
            } catch (\Throwable $e) {
                Log::warning("Pollinations [{$model}] render gagal ({$e->getMessage()}), mencoba model cadangan turbo...");
                try {
                    $imageData = $this->renderWithPollinations(
                        $userPrompt,
                        $aspectRatio,
                        'turbo',
                        $referenceImageUrl,
                        $depthMapUrl
                    );
                } catch (\Throwable $turboErr) {
                    throw new RuntimeException("Gagal me-render dengan Pollinations [{$model}]: " . $turboErr->getMessage());
                }
            }
        }
        else {
            throw new RuntimeException("Provider AI Render '{$provider}' tidak dikenali di .env. Pilihan yang valid: pollinations | gemini | replicate.");
        }

        if (empty($imageData)) {
            throw new RuntimeException('Gagal menghasilkan gambar render AI dari server.');
        }

        // 6. Hapus file AI render lama jika ada
        if ($capture->ai_rendered_path && Storage::disk('public')->exists($capture->ai_rendered_path)) {
            Storage::disk('public')->delete($capture->ai_rendered_path);
        }

        // 7. Simpan file hasil generate
        $filename = 'ai_render_' . Str::uuid() . '.jpg';
        $path = "projects/{$project->id}/captures/ai/{$filename}";
        Storage::disk('public')->put($path, $imageData);

        // 8. Update model capture
        $capture->update([
            'ai_rendered_path' => $path,
            'prompt'           => $userPrompt,
        ]);

        return $capture->fresh();
    }

    /**
     * Dapatkan file biner gambar 3D dari upload atau storage
     */
    protected function getImageBinary(ProjectCapture $capture, ?UploadedFile $uploadedFile = null): ?string
    {
        if ($uploadedFile && $uploadedFile->isValid()) {
            return file_get_contents($uploadedFile->getRealPath());
        }

        if ($capture->file_path && Storage::disk('public')->exists($capture->file_path)) {
            return Storage::disk('public')->get($capture->file_path);
        }

        return null;
    }

    /**
     * Selesaikan pembuatan binary Depth Map: dari upload, storage, atau server-side GD generator
     */
    protected function resolveDepthMapBinary(
        ProjectCapture $capture,
        ?UploadedFile $uploadedDepthMap = null,
        ?string $refBinary = null
    ): ?string {
        // 1. Jika depth map diupload langsung dari browser canvas
        if ($uploadedDepthMap && $uploadedDepthMap->isValid()) {
            return file_get_contents($uploadedDepthMap->getRealPath());
        }

        // 2. Jika depth map sudah pernah tersimpan di storage sebelumnya
        if ($capture->depth_map_path && Storage::disk('public')->exists($capture->depth_map_path)) {
            return Storage::disk('public')->get($capture->depth_map_path);
        }

        // 3. Generate otomatis menggunakan GD di server jika belum ada
        if (!empty($refBinary) && extension_loaded('gd')) {
            return $this->generateDepthMapFromImage($refBinary);
        }

        return null;
    }

    /**
     * Generator Depth Map di server menggunakan ekstensi GD
     */
    protected function generateDepthMapFromImage(string $imageBinary): ?string
    {
        try {
            $src = @imagecreatefromstring($imageBinary);
            if (! $src) {
                return null;
            }

            // Konversi ke grayscale untuk peta kedalaman
            imagefilter($src, IMG_FILTER_GRAYSCALE);
            imagefilter($src, IMG_FILTER_CONTRAST, -10);

            ob_start();
            imagepng($src);
            $output = ob_get_clean();

            return $output ?: null;
        } catch (\Throwable $e) {
            Log::warning('Gagal generate depth map GD: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Dapatkan URL publik untuk file binary agar dapat diunduh oleh API generator AI
     */
    protected function getPublicUrlForBinary(
        ?string $fileContents,
        string $filename = 'image.jpg',
        string $mime = 'image/jpeg',
        ?string $existingUrl = null
    ): ?string {
        if (empty($fileContents)) {
            return null;
        }

        // Jika URL sudah publik online (bukan localhost/127.0.0.1)
        if (!empty($existingUrl) && filter_var($existingUrl, FILTER_VALIDATE_URL)) {
            $host = parse_url($existingUrl, PHP_URL_HOST);
            if ($host && !in_array($host, ['localhost', '127.0.0.1', '::1']) && !str_ends_with($host, '.local') && !str_ends_with($host, '.test')) {
                return $existingUrl;
            }
        }

        // Jika berada di localhost, upload sementara ke bridge tmpfiles.org
        try {
            $response = Http::timeout(25)
                ->attach('file', $fileContents, $filename, ['Content-Type' => $mime])
                ->post('https://tmpfiles.org/api/v1/upload');

            if ($response->successful()) {
                $json = $response->json();
                $pageUrl = $json['data']['url'] ?? null;
                if ($pageUrl) {
                    return str_replace('tmpfiles.org/', 'tmpfiles.org/dl/', $pageUrl);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("TmpFiles upload error ({$filename}): " . $e->getMessage());
        }

        return null;
    }

    /**
     * Ringkas prompt agar efisien dan tidak menyebabkan URL terlalu panjang saat proses render visual
     */
    protected function buildCompactRenderPrompt(string $prompt): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $prompt));
        if (strlen($clean) > 350) {
            return substr($clean, 0, 347) . '...';
        }
        return $clean;
    }

    /**
     * Render via Pollinations.ai dengan dukungan Gambar Referensi & Depth Map
     */
    protected function renderWithPollinations(
        string $prompt,
        string $aspectRatio,
        string $model = 'flux',
        ?string $referenceImageUrl = null,
        ?string $depthMapUrl = null
    ): string {
        [$width, $height] = match ($aspectRatio) {
            '4:3'   => [1024, 768],
            '1:1'   => [1024, 1024],
            default => [1280, 720], // 16:9
        };

        $compactPrompt = $this->buildCompactRenderPrompt($prompt);
        $seed = random_int(1000, 9999999);
        $encodedPrompt = rawurlencode($compactPrompt);
        $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width={$width}&height={$height}&model={$model}&nologo=true&seed={$seed}";

        // Sertakan Gambar Referensi dan Depth Map (ControlNet) jika tersedia
        if (!empty($referenceImageUrl) && !empty($depthMapUrl)) {
            $url .= "&image=" . rawurlencode($referenceImageUrl) . "&image=" . rawurlencode($depthMapUrl);
        } elseif (!empty($referenceImageUrl)) {
            $url .= "&image=" . rawurlencode($referenceImageUrl);
        } elseif (!empty($depthMapUrl)) {
            $url .= "&image=" . rawurlencode($depthMapUrl);
        }

        $pollinationsApiKey = (string) ($this->getSetting('pollinations_api_key', config('services.pollinations.api_key') ?: env('POLLINATIONS_API_KEY')));

        $http = Http::timeout(60);
        if (!empty($pollinationsApiKey)) {
            $http = $http->withToken($pollinationsApiKey);
            $url .= "&key=" . urlencode($pollinationsApiKey);
        }

        $response = $http->get($url);

        // Jika terkena batas kecepatan tier gratis (1 gambar per 45 detik)
        if ($response->status() === 402) {
            throw new RuntimeException("Batas kecepatan render tier publik tercapai (1 gambar per 45 detik per IP). Mohon tunggu sekitar 45 detik sebelum menekan generate kembali.");
        }

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
    protected function renderWithReplicate(
        ?string $apiKey,
        string $prompt,
        string $aspectRatio,
        string $model,
        ?string $referenceImageUrl = null,
        ?string $depthMapUrl = null
    ): string {
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

        if (!empty($depthMapUrl)) {
            $inputPayload['depth_map'] = $depthMapUrl;
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
     * Render via Google Gemini Image API
     */
    protected function callGeminiImagen(?string $apiKey, string $prompt, string $aspectRatio = '16:9', string $requestedModel = 'gemini-3.5-flash-lite', ?string $refBinary = null, ?string $depthMapBinary = null): string
    {
        if (empty($apiKey)) {
            throw new RuntimeException('API Key Gemini belum disetel. Pastikan GEMINI_API_KEY terpasang di .env.');
        }

        // Prioritaskan model yang dikustomisasi di .env
        $cleanRequested = str_replace('models/', '', $requestedModel);

        $modelsToTry = [$cleanRequested];

        // Jika model teks/multimodal yang dipilih (misal gemini-3.5-flash-lite atau gemini-3.5-flash),
        // sertakan varian model image generation yang aktif di Google Gemini API
        if (str_contains($cleanRequested, 'flash-lite')) {
            $modelsToTry[] = 'gemini-3.1-flash-lite-image';
        } elseif (str_contains($cleanRequested, 'flash')) {
            $modelsToTry[] = 'gemini-3.1-flash-image';
        }

        $modelsToTry[] = 'gemini-2.5-flash-image';
        $modelsToTry[] = 'gemini-3-pro-image';

        $models = array_unique(array_filter($modelsToTry));
        
        $primaryException = null;
        $lastException = null;

        // Siapkan parts untuk multimodal (sertakan gambar referensi RGB & Depth Map jika ada)
        $multimodalParts = [];
        if (!empty($refBinary)) {
            $multimodalParts[] = [
                'inlineData' => [
                    'mimeType' => 'image/jpeg',
                    'data'     => base64_encode($refBinary),
                ],
            ];
        }
        if (!empty($depthMapBinary)) {
            $multimodalParts[] = [
                'inlineData' => [
                    'mimeType' => 'image/png',
                    'data'     => base64_encode($depthMapBinary),
                ],
            ];
        }
        $multimodalParts[] = [
            'text' => $prompt,
        ];

        foreach ($models as $model) {
            $isGeminiMultimodal = str_starts_with($model, 'gemini-');

            try {
                if ($isGeminiMultimodal) {
                    // Endpoint untuk model Gemini Image Generation (generateContent)
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                    $response = Http::timeout(60)->post($url, [
                        'contents' => [
                            [
                                'parts' => $multimodalParts,
                            ],
                        ],
                        'generationConfig' => [
                            'responseModalities' => ['IMAGE'],
                        ],
                    ]);

                    if ($response->successful()) {
                        $json = $response->json();
                        $candidate = $json['candidates'][0] ?? null;
                        $parts = $candidate['content']['parts'] ?? [];
                        foreach ($parts as $part) {
                            $inlineData = $part['inlineData'] ?? $part['inline_data'] ?? null;
                            if (!empty($inlineData['data'])) {
                                $binary = base64_decode($inlineData['data']);
                                if ($binary !== false) {
                                    return $binary;
                                }
                            }
                        }

                        // Jika kandidat tidak menghasilkan gambar (misalnya finishReason: NO_IMAGE pada model teks)
                        $finishReason = $candidate['finishReason'] ?? 'NO_IMAGE';
                        $errText = "Model {$model} mengembalikan status {$finishReason} tanpa data gambar (model ini adalah vision/text understanding, bukan generator gambar langsung).";
                        $lastException = new RuntimeException($errText);
                        if ($model === $cleanRequested) {
                            $primaryException = $lastException;
                        }
                        continue;
                    }
                } else {
                    // Endpoint untuk Google Imagen (predict)
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:predict?key={$apiKey}";
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
                }

                $errorMsg = $response->json('error.message') ?? $response->body();
                $lastException = new RuntimeException("Gagal me-render dengan {$model}: {$errorMsg}");
                if ($model === $cleanRequested) {
                    $primaryException = $lastException;
                }
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($model === $cleanRequested) {
                    $primaryException = $lastException;
                }
            }
        }

        throw $primaryException ?: $lastException ?: new RuntimeException('Gagal menghubungi API Gemini Image Generation.');
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
