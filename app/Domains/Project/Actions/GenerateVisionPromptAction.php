<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateVisionPromptAction
{
    public function __construct(
        protected ?SystemSettingRepository $settingsRepo = null
    ) {
        $this->settingsRepo = $settingsRepo ?? app(SystemSettingRepository::class);
    }

    public function execute(?User $user, Project $project, array $data): array
    {
        $metadata = $data['scene_metadata'] ?? [];
        $rawImage = $data['image'] ?? '';

        $fallbackPrompt = $this->buildFallbackPrompt($project, $metadata);

        // 1. Resolusi Konfigurasi: Database (Prioritas) -> Config .env (Fallback)
        $aiConfig = $this->resolveAiConfig();
        $provider = $aiConfig['provider'];
        $apiKey = $aiConfig['api_key'] ?? '';
        $model = $aiConfig['model'] ?? '';

        if (empty($apiKey)) {
            $providerUpper = strtoupper($provider);
            return [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => "API Key untuk provider {$providerUpper} belum disetel di Database maupun .env. Menggunakan generator spasial 3D internal.",
            ];
        }

        if (empty($rawImage)) {
            return [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => 'Gambar kanvas tidak tersedia. Menggunakan generator spasial 3D.',
            ];
        }

        try {
            // Ekstrak MIME type dan data murni Base64
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $rawImage, $matches)) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
            } else {
                $mimeType = 'image/png';
                $base64Data = $rawImage;
            }

            $systemInstruction = <<<TEXT
You are an expert Architectural Visualization Prompt Engineer. Analyze the attached 3D .glb viewport screenshot and generate ONE single image-to-image rendering prompt in English.

CRITICAL RULES FOR STRUCTURE & COLOR ACCURACY:
1. Start the prompt with this exact mandatory command:
   "Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image."
2. EXACT COLOR MAPPING: Look closely at the colors in the 3D screenshot. Explicitly map each part of the building to its exact visible color shade (e.g., "keep the off-white walls off-white", "preserve the light beige floor tiles", "maintain the dark charcoal window frames", "keep the warm honey-oak wood color on the ceiling").
3. DO NOT invent new materials that change the original color (e.g., if a wall is plain white in the 3D image, describe it as "smooth white painted stucco wall", NEVER change it to grey concrete or red brick).
4. NEUTRALIZE LIGHTING COLOR CAST: Describe the lighting in terms of shadow direction and brightness ONLY, adding "neutral white balance, accurate true-to-life surface colors without heavy color tinting".
5. Output ONLY the final prompt text without markdown or explanations.
TEXT;

            $cutKeywords = $metadata['cut_keywords'] ?? ($metadata['clipping_desc'] ?? (!empty($metadata['clipping_enabled']) ? 'Active Section Cut' : 'Solid intact architectural massing, unclipped complete geometry'));
            $angleKeywords = $metadata['angle_keywords'] ?? trim(($metadata['camera_perspective'] ?? 'straight-on rectilinear architectural framing') . ', ' . ($metadata['shot_type'] ?? '35mm lens'));
            $lightingKeywords = $metadata['lighting_keywords'] ?? ($metadata['lighting_desc'] ?? 'crisp bright mid-day sun, sharp geometric architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting');

            $userPrompt = <<<PROMPT
You are an expert Architectural Visualization Prompt Engineer. Analyze the attached 3D .glb viewport screenshot and generate ONE single image-to-image rendering prompt in English.

SCENE METADATA:
- View & Section State: {$cutKeywords}
- Camera Angle: {$angleKeywords}
- Lighting State: {$lightingKeywords}

CRITICAL RULES FOR STRUCTURE & COLOR ACCURACY:
1. Start the prompt with this exact mandatory command:
   "Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image."
2. EXACT COLOR MAPPING: Look closely at the colors in the 3D screenshot. Explicitly map each part of the building to its exact visible color shade (e.g., "keep the off-white walls off-white", "preserve the light beige floor tiles", "maintain the dark charcoal window frames", "keep the warm honey-oak wood color on the ceiling").
3. DO NOT invent new materials that change the original color (e.g., if a wall is plain white in the 3D image, describe it as "smooth white painted stucco wall", NEVER change it to grey concrete or red brick).
4. NEUTRALIZE LIGHTING COLOR CAST: Describe the lighting ({$lightingKeywords}) in terms of shadow direction and brightness ONLY, adding "neutral white balance, accurate true-to-life surface colors without heavy color tinting".
5. Output ONLY the final prompt text without markdown or explanations.
PROMPT;

            // Dispatch ke Provider aktif
            $rawResponseText = match ($provider) {
                'openai' => $this->callOpenAi($aiConfig, $systemInstruction, $userPrompt, $mimeType, $base64Data),
                'claude' => $this->callClaude($aiConfig, $systemInstruction, $userPrompt, $mimeType, $base64Data),
                'ollama' => $this->callOllama($aiConfig, $systemInstruction, $userPrompt, $base64Data),
                default  => $this->callGemini($aiConfig, $systemInstruction, $userPrompt, $mimeType, $base64Data),
            };

            if (!empty($rawResponseText)) {
                $cleanedText = $this->sanitizePromptText($rawResponseText);

                return [
                    'prompt'   => $cleanedText,
                    'source'   => "{$provider}-vision",
                    'provider' => $provider,
                    'model'    => $model,
                ];
            }

            return [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => "Layanan Vision AI ({$provider}) sedang sibuk. Menggunakan generator spasial 3D internal.",
            ];
        } catch (\Throwable $e) {
            Log::error("Vision AI exception [{$provider}]", [
                'error' => $e->getMessage(),
            ]);

            return [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => "Gagal menghubungi Vision AI ({$provider}). Menggunakan generator spasial 3D internal.",
            ];
        }
    }

    /**
     * Resolusi konfigurasi: Database (tabel system_settings) -> .env (config/services.php)
     */
    protected function resolveAiConfig(): array
    {
        $dbProvider = $this->getSetting('vision_ai_provider');
        $provider = strtolower($dbProvider ?: config('services.vision_ai.provider', 'gemini'));

        return match ($provider) {
            'openai' => [
                'provider' => 'openai',
                'api_key'  => $this->getSetting('openai_api_key', config('services.vision_ai.providers.openai.api_key')),
                'model'    => $this->getSetting('openai_model', config('services.vision_ai.providers.openai.model', 'gpt-4o-mini')),
                'base_url' => $this->getSetting('openai_base_url', config('services.vision_ai.providers.openai.base_url', 'https://api.openai.com/v1')),
            ],
            'claude' => [
                'provider' => 'claude',
                'api_key'  => $this->getSetting('claude_api_key', config('services.vision_ai.providers.claude.api_key')),
                'model'    => $this->getSetting('claude_model', config('services.vision_ai.providers.claude.model', 'claude-3-5-sonnet-20241022')),
            ],
            'ollama' => [
                'provider' => 'ollama',
                'api_key'  => 'ollama-local',
                'model'    => $this->getSetting('ollama_model', config('services.vision_ai.providers.ollama.model', 'llava')),
                'base_url' => $this->getSetting('ollama_base_url', config('services.vision_ai.providers.ollama.base_url', 'http://localhost:11434')),
            ],
            default => [
                'provider' => 'gemini',
                'api_key'  => $this->getSetting('gemini_api_key', config('services.vision_ai.providers.gemini.api_key') ?: config('services.gemini.api_key')),
                'model'    => $this->getSetting('gemini_model', config('services.vision_ai.providers.gemini.model') ?: config('services.gemini.model', 'gemini-2.5-flash')),
            ],
        };
    }

    protected function getSetting(string $key, mixed $default = null): mixed
    {
        try {
            $val = $this->settingsRepo?->get($key);
            if (!empty($val)) {
                return $val;
            }
        } catch (\Throwable) {
            // Abaikan jika DB belum siap
        }
        return $default;
    }

    protected function callGemini(array $config, string $systemInstruction, string $userPrompt, string $mimeType, string $base64Data): ?string
    {
        $apiKey = $config['api_key'];
        $model = $config['model'] ?: 'gemini-2.5-flash';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(25)->post($url, [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userPrompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Data,
                            ],
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1024,
            ],
        ]);

        if ($response->successful()) {
            return $response->json('candidates.0.content.parts.0.text');
        }

        Log::warning('Gemini Vision API error', [
            'status' => $response->status(),
            'body'   => substr($response->body(), 0, 500),
        ]);

        return null;
    }

    protected function callOpenAi(array $config, string $systemInstruction, string $userPrompt, string $mimeType, string $base64Data): ?string
    {
        $apiKey = $config['api_key'];
        $model = $config['model'] ?: 'gpt-4o-mini';
        $baseUrl = rtrim($config['base_url'] ?: 'https://api.openai.com/v1', '/');
        $url = "{$baseUrl}/chat/completions";

        $response = Http::timeout(25)
            ->withToken($apiKey)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemInstruction],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $userPrompt],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$base64Data}",
                                ],
                            ],
                        ],
                    ],
                ],
                'temperature' => 0.2,
                'max_tokens'  => 1024,
            ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content');
        }

        Log::warning('OpenAI Vision API error', [
            'status' => $response->status(),
            'body'   => substr($response->body(), 0, 500),
        ]);

        return null;
    }

    protected function callClaude(array $config, string $systemInstruction, string $userPrompt, string $mimeType, string $base64Data): ?string
    {
        $apiKey = $config['api_key'];
        $model = $config['model'] ?: 'claude-3-5-sonnet-20241022';
        $url = 'https://api.anthropic.com/v1/messages';

        $response = Http::timeout(25)
            ->withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])
            ->post($url, [
                'model'      => $model,
                'max_tokens' => 1024,
                'system'     => $systemInstruction,
                'messages'   => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => $mimeType,
                                    'data'       => $base64Data,
                                ],
                            ],
                            ['type' => 'text', 'text' => $userPrompt],
                        ],
                    ],
                ],
            ]);

        if ($response->successful()) {
            return $response->json('content.0.text');
        }

        Log::warning('Claude Vision API error', [
            'status' => $response->status(),
            'body'   => substr($response->body(), 0, 500),
        ]);

        return null;
    }

    protected function callOllama(array $config, string $systemInstruction, string $userPrompt, string $base64Data): ?string
    {
        $model = $config['model'] ?: 'llava';
        $baseUrl = rtrim($config['base_url'] ?: 'http://localhost:11434', '/');
        $url = "{$baseUrl}/api/generate";

        $response = Http::timeout(35)->post($url, [
            'model'  => $model,
            'system' => $systemInstruction,
            'prompt' => $userPrompt,
            'images' => [$base64Data],
            'stream' => false,
        ]);

        if ($response->successful()) {
            return $response->json('response');
        }

        Log::warning('Ollama Vision API error', [
            'status' => $response->status(),
            'body'   => substr($response->body(), 0, 500),
        ]);

        return null;
    }

    protected function sanitizePromptText(string $candidateText): string
    {
        $cleanedText = trim($candidateText);
        $cleanedText = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $cleanedText);
        $cleanedText = trim($cleanedText);
        $cleanedText = preg_replace('/^"|"$/', '', $cleanedText);
        $cleanedText = trim($cleanedText);

        $mandatoryPrefix = 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image.';
        if (!str_starts_with($cleanedText, 'Strict image-to-image')) {
            $cleanedText = $mandatoryPrefix . ' ' . $cleanedText;
        }

        return $cleanedText;
    }

    private function buildFallbackPrompt(Project $project, array $metadata): string
    {
        $mandatoryPrefix = 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image.';

        $subject = $metadata['detected_object'] ?? ($metadata['room_name'] ?? 'Modern Architectural Space');
        $materials = (array) ($metadata['detected_materials'] ?? []);
        $matPhrase = !empty($materials)
            ? 'Preserve visible material palette: ' . implode(', ', array_slice($materials, 0, 4)) . '.'
            : 'Preserve authentic visible surface materials and true colors.';

        $cutKeywords = $metadata['cut_keywords'] ?? ($metadata['clipping_desc'] ?? (!empty($metadata['clipping_enabled']) ? 'Architectural section cut' : 'Solid intact architectural massing, unclipped complete geometry'));
        $angleKeywords = $metadata['angle_keywords'] ?? trim(($metadata['camera_perspective'] ?? 'straight-on rectilinear architectural framing') . ', ' . ($metadata['shot_type'] ?? '35mm lens'));
        $lightingKeywords = $metadata['lighting_keywords'] ?? ($metadata['lighting_desc'] ?? 'crisp natural daylight with directional architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting');

        $projName = $project->title ? 'for ' . $project->title : 'for this architectural project';

        return "{$mandatoryPrefix} Viewport capture of {$subject} {$projName}. {$cutKeywords}. Camera perspective: {$angleKeywords}. Lighting: {$lightingKeywords}. {$matPhrase} Shot on Hasselblad H6D-100c, 35mm lens, f/8, ambient occlusion crevices, raytraced global illumination, 8k resolution, award-winning architectural showcase.";
    }
}
