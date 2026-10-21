<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateVisionPromptAction
{
    public const MOTION_DESCRIPTIONS = [
        'two_point'     => 'seamless camera transition traveling steadily from starting camera viewpoint in Frame 1 to destination viewpoint in Frame 2',
        'push_in'       => 'smooth continuous forward dolly push-in tracking shot approaching and entering the architectural space',
        'pull_out'      => 'smooth reverse dolly pull-out reveal shot moving steadily backward to unveil the complete architectural facade',
        'orbit_left'    => 'continuous circular orbit tracking arc traveling clockwise around the building facade',
        'orbit_right'   => 'continuous circular orbit tracking arc traveling counter-clockwise around the building facade',
        'pan_left'      => 'smooth horizontal lateral pan tracking across the facade to the left',
        'pan_right'     => 'smooth horizontal lateral pan tracking across the facade to the right',
        'crane_up'      => 'vertical cinematic crane pedestal-up ascent rising steadily upwards to reveal upper volumes and rooflines',
        'crane_down'    => 'gentle descending crane pedestal-down landing smoothly towards eye-level ground perspective',
        'fpv_drone'     => 'smooth stabilized FPV drone flight gliding effortlessly through the architectural space and openings',
        'spiral_ascend' => 'helical spiral ascension combining continuous circular orbit with steady vertical elevation gain',
        'tilt_up'       => 'cinematic vertical tilt-up pivot starting from ground foundation and sweeping up to the skyline',
        'tilt_down'     => 'cinematic vertical tilt-down pivot looking downward from ceiling to floor layout',
        'timelapse'     => 'static locked-off framing with dynamic daylight sun-path timelapse and crisp shifting geometric shadows',
        'static_subtle' => 'rock-solid tripod framing with subtle organic micro-drift parallax and natural atmospheric movement',
    ];

    public const DEFAULT_VIDEO_NEGATIVE_PROMPT = 'deformed architecture, morphing walls, bending columns, warped windows, flickering geometry, sudden camera cuts, jerky movement, erratic zoom, floating artifacts, distorted proportions, low framerate, jittery shadows';

    public function __construct(
        protected ?SystemSettingRepository $settingsRepo = null
    ) {
        $this->settingsRepo = $settingsRepo ?? app(SystemSettingRepository::class);
    }

    public function execute(?User $user, ?Project $project = null, array $data = []): array
    {
        $metadata = $data['scene_metadata'] ?? [];
        $rawImage = $data['image'] ?? '';
        $rawEndImage = $data['end_image'] ?? '';
        $promptType = $data['prompt_type'] ?? 'image'; // 'image' | 'video'

        // Multi-motion support
        $motions = (array) ($data['camera_motions'] ?? (!empty($data['camera_motion']) ? [$data['camera_motion']] : ['push_in']));
        $duration = $data['duration'] ?? '5s';
        $cameraPace = $data['camera_pace'] ?? 'slow';
        $ambientMood = $data['ambient_mood'] ?? 'scene';
        $lensStyle = $data['lens_style'] ?? 'anamorphic_35mm';
        $strictGeometry = isset($data['strict_geometry']) ? (bool) $data['strict_geometry'] : true;

        $videoTarget = $data['video_target'] ?? 'runway'; // 'runway' | 'kling' | 'luma' | 'universal'
        $hasEndFrame = !empty($rawEndImage);

        $fallbackPrompt = $this->buildFallbackPrompt(
            $project,
            $metadata,
            $promptType,
            $motions,
            $hasEndFrame,
            $duration,
            $cameraPace,
            $ambientMood,
            $lensStyle,
            $strictGeometry
        );

        // 1. Resolusi Konfigurasi: Database (Prioritas) -> Config .env (Fallback)
        $aiConfig = $this->resolveAiConfig();
        $provider = $aiConfig['provider'];
        $apiKey = $aiConfig['api_key'] ?? '';
        $model = $aiConfig['model'] ?? '';

        $baseResponse = [
            'type'            => $promptType,
            'camera_motion'   => $motions[0] ?? 'push_in',
            'camera_motions'  => $motions,
            'duration'        => $duration,
            'camera_pace'     => $cameraPace,
            'ambient_mood'    => $ambientMood,
            'lens_style'      => $lensStyle,
            'has_end_frame'   => $hasEndFrame,
            'negative_prompt' => $promptType === 'video' ? self::DEFAULT_VIDEO_NEGATIVE_PROMPT : null,
        ];

        if (empty($apiKey)) {
            $providerUpper = strtoupper($provider);
            return array_merge($baseResponse, [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => "API Key untuk provider {$providerUpper} belum disetel di Database maupun .env. Menggunakan generator spasial 3D internal.",
            ]);
        }

        if (empty($rawImage)) {
            return array_merge($baseResponse, [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => 'Gambar kanvas tidak tersedia. Menggunakan generator spasial 3D.',
            ]);
        }

        try {
            // Ekstrak Frame 1 (Start Frame)
            $parsedStart = $this->parseBase64Image($rawImage);
            $images = [
                [
                    'mime_type' => $parsedStart['mime_type'],
                    'base64'    => $parsedStart['base64'],
                    'label'     => $hasEndFrame ? 'START FRAME (Position A)' : 'REFERENCE VIEWPORT',
                ],
            ];

            // Ekstrak Frame 2 (End Frame / Posisi Akhir) jika ada
            if ($hasEndFrame) {
                $parsedEnd = $this->parseBase64Image($rawEndImage);
                $images[] = [
                    'mime_type' => $parsedEnd['mime_type'],
                    'base64'    => $parsedEnd['base64'],
                    'label'     => 'END FRAME (Position B)',
                ];
            }

            // Siapkan Prompt Instruksi (System & User)
            $systemInstruction = $this->buildSystemInstruction($promptType, $motions, $hasEndFrame, $videoTarget);
            $userPrompt = $this->buildUserPrompt(
                $promptType,
                $motions,
                $hasEndFrame,
                $videoTarget,
                $metadata,
                $duration,
                $cameraPace,
                $ambientMood,
                $lensStyle,
                $strictGeometry
            );

            // Dispatch ke Provider aktif
            $rawResponseText = match ($provider) {
                'openai' => $this->callOpenAi($aiConfig, $systemInstruction, $userPrompt, $images),
                'claude' => $this->callClaude($aiConfig, $systemInstruction, $userPrompt, $images),
                'ollama' => $this->callOllama($aiConfig, $systemInstruction, $userPrompt, $images),
                default  => $this->callGemini($aiConfig, $systemInstruction, $userPrompt, $images),
            };

            if (!empty($rawResponseText)) {
                $cleanedText = $this->sanitizePromptText($rawResponseText, $promptType);

                return array_merge($baseResponse, [
                    'prompt'   => $cleanedText,
                    'source'   => "{$provider}-vision",
                    'provider' => $provider,
                    'model'    => $model,
                ]);
            }

            return array_merge($baseResponse, [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => "Layanan Vision AI ({$provider}) sedang sibuk. Menggunakan generator spasial 3D internal.",
            ]);
        } catch (\Throwable $e) {
            Log::error("Vision AI exception [{$provider}]", [
                'error' => $e->getMessage(),
            ]);

            return array_merge($baseResponse, [
                'prompt'   => $fallbackPrompt,
                'source'   => 'local-spatial-3d',
                'provider' => $provider,
                'notice'   => "Gagal menghubungi Vision AI ({$provider}). Menggunakan generator spasial 3D internal.",
            ]);
        }
    }

    protected function parseBase64Image(string $rawImage): array
    {
        if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-\.]+);base64,(.+)$/', $rawImage, $matches)) {
            return [
                'mime_type' => $matches[1],
                'base64'    => $matches[2],
            ];
        }

        return [
            'mime_type' => 'image/png',
            'base64'    => $rawImage,
        ];
    }

    protected function formatCameraMotions(array $motions): string
    {
        $phrases = [];
        foreach ($motions as $m) {
            if (isset(self::MOTION_DESCRIPTIONS[$m])) {
                $phrases[] = self::MOTION_DESCRIPTIONS[$m];
            }
        }

        if (empty($phrases)) {
            return 'Smooth continuous forward tracking shot through the architectural space.';
        }

        if (count($phrases) === 1) {
            return ucfirst($phrases[0]) . '.';
        }

        return 'Simultaneous compound camera movement combining: ' . implode(', and ', $phrases) . '.';
    }

    protected function buildSystemInstruction(string $promptType, array|string $motions, bool $hasEndFrame, ?string $videoTarget): string
    {
        if ($promptType === 'video') {
            if ($hasEndFrame) {
                return <<<TEXT
You are an expert Architectural Cinematographer and AI Video Prompt Engineer specializing in Runway Gen-3 Alpha, Kling AI, and Luma Dream Machine.
Analyze the two attached 3D architectural viewport screenshots:
- Image 1 is the START FRAME (First Frame / initial camera position).
- Image 2 is the END FRAME (Last Frame / destination camera position).

Generate ONE concise, powerful image-to-video prompt in English tailored for modern AI video tools that accept First Frame and Last Frame inputs.

MANDATORY RULES:
1. Start the prompt with the exact continuous camera motion command transitioning smoothly from Frame 1 to Frame 2 (e.g., "Cinematic smooth continuous forward tracking shot seamlessly traveling from the exterior terrace in Frame 1 into the spacious interior living room in Frame 2").
2. Maintain 100% strict architectural geometry, spatial proportions, surface textures, and THE EXACT COLOR PALETTE of every surface visible in both frames.
3. Describe subtle realistic environmental dynamics: gentle atmospheric breeze rustling surrounding plants, soft dynamic natural daylight with subtle shifting architectural shadows, warm interior ambient lights glowing.
4. Conclude with technical camera specifications: "Shot on Arri Alexa 65, 35mm anamorphic lens, 24fps, photorealistic architectural film reel, hyper-realistic, fluid motion without warping or morphing."
5. Output ONLY the final prompt text without markdown, quotes, or conversational explanations.
TEXT;
            }

            return <<<TEXT
You are an expert Architectural Cinematographer and AI Video Prompt Engineer specializing in cinematic video generation (Runway Gen-3, Kling AI, Luma Dream Machine, Sora).
Analyze the attached 3D .glb architectural viewport screenshot and generate ONE single image-to-video prompt in English.

MANDATORY RULES:
1. Start the prompt with this exact opening style:
   "Cinematic architectural video rendering of the attached 3D reference. Maintain 100% of the exact geometry, spatial proportions, and surface colors with fluid cinematic camera motion."
2. Clearly describe the requested compound camera motion trajectory based on the selected camera motion styles.
3. EXACT COLOR & MATERIAL FIDELITY: Look closely at visible surfaces in the 3D screenshot. Preserve every exact color shade and texture without altering materials.
4. Describe subtle realistic environmental dynamics: gentle atmospheric breeze, dynamic natural lighting with crisp architectural shadows, warm interior glow.
5. Conclude with cinematic technical specifications: "Shot on 35mm anamorphic lens, 24fps, photorealistic architectural film reel, hyper-realistic, fluid motion without warping."
6. Output ONLY the final prompt text without markdown or conversational explanations.
TEXT;
        }

        // Image-to-Image System Instruction
        return <<<TEXT
You are an expert Architectural Visualization Prompt Engineer. Analyze the attached 3D .glb viewport screenshot and generate ONE single image-to-image rendering prompt in English.

CRITICAL RULES FOR PHOTOREALISM & COLOR ACCURACY:
1. Start the prompt with this exact mandatory command:
   "Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Transform this 3D model viewport into a magazine-grade, real-life architectural photograph. Maintain 100% of the exact geometry, camera perspective, section-cut lines, and color palette of every surface, but replace all raw 3D polygon shaders with authentic, tactile physical materials."
2. EXACT COLOR & MATERIAL MAPPING: Look closely at visible surfaces in the 3D screenshot. Explicitly map each part of the building to authentic real-world materials while preserving its visible color shade (e.g., "smooth matte off-white painted stucco walls", "honed light beige travertine floor tiles with subtle grout lines", "fine natural oak wood paneling with realistic grain", "powder-coated dark charcoal aluminum window frames with crystal-clear architectural glass reflections").
3. REALISTIC PHYSICAL TEXTURES: Transform flat computer-generated polygons into realistic physical architectural materials with subtle micro-textures, real-life material depth, genuine wood grain, authentic stone pores, and natural paint finishes. Ensure the image looks like an actual built structure photographed in the real world, NEVER a 3D CAD model, cartoon, or computer rendering.
4. NEUTRALIZE LIGHTING & SHADOWS: Describe natural architectural illumination: "crisp natural daylight with soft geometric shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting, subtle ambient occlusion in corners".
5. Conclude with camera specs: "Shot on Hasselblad H6D-100c, 35mm lens, f/8, natural soft illumination, Architectural Digest magazine photograph."
6. Output ONLY the final prompt text without markdown, quotes, or conversational explanations.
TEXT;
    }

    protected function buildUserPrompt(
        string $promptType,
        array|string $motions,
        bool $hasEndFrame,
        ?string $videoTarget,
        array $metadata,
        string $duration = '5s',
        string $cameraPace = 'slow',
        string $ambientMood = 'scene',
        string $lensStyle = 'anamorphic_35mm',
        bool $strictGeometry = true
    ): string {
        $cutKeywords = $metadata['cut_keywords'] ?? ($metadata['clipping_desc'] ?? (!empty($metadata['clipping_enabled']) ? 'Active Section Cut' : 'Solid intact architectural massing, unclipped complete geometry'));
        $angleKeywords = $metadata['angle_keywords'] ?? trim(($metadata['camera_perspective'] ?? 'straight-on rectilinear architectural framing') . ', ' . ($metadata['shot_type'] ?? '35mm lens'));
        $lightingKeywords = $metadata['lighting_keywords'] ?? ($metadata['lighting_desc'] ?? 'crisp bright mid-day sun, sharp geometric architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting');

        if ($promptType === 'video') {
            $motionsArray = is_array($motions) ? $motions : [$motions];
            $motionDescription = $this->formatCameraMotions($motionsArray);

            $paceDescription = match ($cameraPace) {
                'slow'    => 'Slow and majestic camera velocity (0.5x speed) designed to preserve razor-sharp architectural geometry without motion blur',
                'dynamic' => 'Dynamic fluid cinematic velocity',
                default   => 'Steady natural cinematic walking velocity (1.0x speed)',
            };

            $moodDescription = match ($ambientMood) {
                'golden_hour'   => 'Warm golden hour sunset, elongated geometric architectural shadows, warm amber ray lighting',
                'overcast'      => 'Soft overcast diffuse daylight, minimalist even illumination',
                'rainy'         => 'Cinematic dusk rain with soft droplets, reflective wet asphalt and exterior glass reflections, moody luxury ambiance',
                'night_luxury'  => 'Dramatic evening twilight, warm interior spotlights and linear LED ribbons glowing through glass',
                'misty_morning' => 'Atmospheric dawn mist with volumetric Tyndall sunbeams filtering through openings',
                default         => $lightingKeywords,
            };

            $lensDescription = match ($lensStyle) {
                'wide_16mm'  => 'Shot on 16mm ultra-wide rectilinear architectural lens, zero optical distortion, straight vertical lines',
                'macro_50mm' => 'Shot on 50mm f/2.0 portrait lens, gentle background bokeh emphasizing tactile textures',
                default      => 'Shot on Arri Alexa 65, 35mm anamorphic prime lens, subtle cinematic depth of field, 24fps film reel',
            };

            $geometryConstraint = $strictGeometry
                ? 'Strict structural preservation: 100% rigid architectural geometry, straight vertical perspective lines, zero warping or morphing of walls, windows, and structural columns.'
                : 'Fluid motion without morphing.';

            if ($hasEndFrame) {
                return <<<PROMPT
SCENE & VIDEO TRAJECTORY:
- Image 1: START FRAME (Initial camera position)
- Image 2: END FRAME (Final camera position)
- Camera Motion: {$motionDescription}
- Duration & Velocity: {$duration} continuous cinematic shot with {$paceDescription}
- Lighting & Atmosphere: {$moodDescription}
- Lens & Cinematography: {$lensDescription}
- Section State: {$cutKeywords}
- Target Video AI: {$videoTarget}
- Quality Guardrail: {$geometryConstraint}

TASK:
Write the complete English video prompt describing the smooth cinematic camera flight from Frame 1 to Frame 2, strictly preserving all visible architectural geometry, colors, and textures, with subtle natural ambient movement. Output ONLY the prompt text.
PROMPT;
            }

            return <<<PROMPT
SCENE & VIDEO PARAMETERS:
- Camera Motion: {$motionDescription}
- Duration & Velocity: {$duration} continuous cinematic shot with {$paceDescription}
- Initial Framing: {$angleKeywords}
- Lighting & Atmosphere: {$moodDescription}
- Lens & Cinematography: {$lensDescription}
- Section State: {$cutKeywords}
- Target Video AI: {$videoTarget}
- Quality Guardrail: {$geometryConstraint}

TASK:
Write the complete English video prompt describing the camera trajectory and spatial atmosphere. Maintain exact colors and materials from the attached 3D reference. Output ONLY the prompt text.
PROMPT;
        }

        return <<<PROMPT
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

    protected function callGemini(array $config, string $systemInstruction, string $userPrompt, array $images): ?string
    {
        $apiKey = $config['api_key'];
        $primaryModel = $config['model'] ?: 'gemini-3.8-flash';
        $modelsToTry = array_unique([$primaryModel, 'gemini-3.8-flash', 'gemini-3.7-flash']);

        $parts = [
            ['text' => $userPrompt],
        ];

        foreach ($images as $img) {
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $img['mime_type'],
                    'data'      => $img['base64'],
                ],
            ];
        }

        $payload = [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.2,
                'maxOutputTokens' => 1024,
            ],
        ];

        foreach ($modelsToTry as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            try {
                $response = Http::timeout(60)->post($url, $payload);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if (!empty($text)) {
                        return $text;
                    }
                }

                Log::warning("Gemini Vision API error ({$model})", [
                    'status' => $response->status(),
                    'body'   => substr($response->body(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                Log::warning("Gemini Vision API exception ({$model}): " . $e->getMessage());
            }
        }

        return null;
    }

    protected function callOpenAi(array $config, string $systemInstruction, string $userPrompt, array $images): ?string
    {
        $apiKey = $config['api_key'];
        $model = $config['model'] ?: 'gpt-4o-mini';
        $baseUrl = rtrim($config['base_url'] ?: 'https://api.openai.com/v1', '/');
        $url = "{$baseUrl}/chat/completions";

        $content = [
            ['type' => 'text', 'text' => $userPrompt],
        ];

        foreach ($images as $img) {
            $content[] = [
                'type'      => 'image_url',
                'image_url' => [
                    'url' => "data:{$img['mime_type']};base64,{$img['base64']}",
                ],
            ];
        }

        $response = Http::timeout(30)
            ->withToken($apiKey)
            ->post($url, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemInstruction],
                    [
                        'role'    => 'user',
                        'content' => $content,
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

    protected function callClaude(array $config, string $systemInstruction, string $userPrompt, array $images): ?string
    {
        $apiKey = $config['api_key'];
        $model = $config['model'] ?: 'claude-3-5-sonnet-20241022';
        $url = 'https://api.anthropic.com/v1/messages';

        $content = [];

        foreach ($images as $img) {
            $content[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => $img['mime_type'],
                    'data'       => $img['base64'],
                ],
            ];
        }

        $content[] = ['type' => 'text', 'text' => $userPrompt];

        $response = Http::timeout(30)
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
                        'role'    => 'user',
                        'content' => $content,
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

    protected function callOllama(array $config, string $systemInstruction, string $userPrompt, array $images): ?string
    {
        $model = $config['model'] ?: 'llava';
        $baseUrl = rtrim($config['base_url'] ?: 'http://localhost:11434', '/');
        $url = "{$baseUrl}/api/generate";

        $response = Http::timeout(35)->post($url, [
            'model'  => $model,
            'system' => $systemInstruction,
            'prompt' => $userPrompt,
            'images' => array_column($images, 'base64'),
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

    protected function sanitizePromptText(string $candidateText, string $promptType = 'image'): string
    {
        $cleanedText = trim($candidateText);
        $cleanedText = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $cleanedText);
        $cleanedText = trim($cleanedText);
        $cleanedText = preg_replace('/^"|"$/', '', $cleanedText);
        $cleanedText = trim($cleanedText);

        if ($promptType === 'video') {
            $mandatoryPrefix = 'Cinematic architectural video rendering of the attached 3D reference. Maintain 100% of the exact geometry, spatial proportions, and surface colors with fluid cinematic camera motion.';
            if (!str_starts_with($cleanedText, 'Cinematic') && !str_starts_with($cleanedText, 'Continuous')) {
                $cleanedText = $mandatoryPrefix . ' ' . $cleanedText;
            }
            return $cleanedText;
        }

        $mandatoryPrefix = 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Transform this 3D model viewport into a magazine-grade, real-life architectural photograph. Maintain 100% of the exact geometry, camera perspective, section-cut lines, and color palette of every surface, but replace all raw 3D polygon shaders with authentic, tactile physical materials.';
        if (!str_starts_with($cleanedText, 'Strict image-to-image')) {
            $cleanedText = $mandatoryPrefix . ' ' . $cleanedText;
        }

        return $cleanedText;
    }

    private function buildFallbackPrompt(
        ?Project $project = null,
        array $metadata = [],
        string $promptType = 'image',
        array|string $motions = ['push_in'],
        bool $hasEndFrame = false,
        string $duration = '5s',
        string $cameraPace = 'slow',
        string $ambientMood = 'scene',
        string $lensStyle = 'anamorphic_35mm',
        bool $strictGeometry = true
    ): string {
        $subject = $metadata['detected_object'] ?? ($metadata['room_name'] ?? 'Modern Architectural Space');
        $materials = (array) ($metadata['detected_materials'] ?? []);
        $matPhrase = !empty($materials)
            ? 'Preserve visible material palette: ' . implode(', ', array_slice($materials, 0, 4)) . '.'
            : 'Preserve authentic visible surface materials and true colors.';

        $cutKeywords = $metadata['cut_keywords'] ?? ($metadata['clipping_desc'] ?? (!empty($metadata['clipping_enabled']) ? 'Architectural section cut' : 'Solid intact architectural massing, unclipped complete geometry'));
        $angleKeywords = $metadata['angle_keywords'] ?? trim(($metadata['camera_perspective'] ?? 'straight-on rectilinear architectural framing') . ', ' . ($metadata['shot_type'] ?? '35mm lens'));
        $lightingKeywords = $metadata['lighting_keywords'] ?? ($metadata['lighting_desc'] ?? 'crisp natural daylight with directional architectural shadows, neutral white balance, accurate true-to-life surface colors without heavy color tinting');
        $projName = $project?->title ? 'for ' . $project->title : 'for this architectural project';

        if ($promptType === 'video') {
            $motionsArray = is_array($motions) ? $motions : [$motions];
            $motionPhrase = $this->formatCameraMotions($motionsArray);

            $moodPhrase = match ($ambientMood) {
                'golden_hour'   => 'Warm golden hour sunset, elongated geometric architectural shadows, warm amber ray lighting',
                'overcast'      => 'Soft overcast diffuse daylight, minimalist even illumination',
                'rainy'         => 'Cinematic dusk rain with soft droplets, reflective wet surfaces and glass reflections',
                'night_luxury'  => 'Dramatic evening twilight, warm interior spotlights and linear LED ribbons glowing through glass',
                'misty_morning' => 'Atmospheric dawn mist with volumetric Tyndall sunbeams filtering through openings',
                default         => $lightingKeywords,
            };

            $pacePhrase = match ($cameraPace) {
                'slow'    => 'Slow and majestic camera velocity (0.5x speed) preserving razor-sharp architectural geometry without motion blur',
                'dynamic' => 'Dynamic fluid cinematic velocity',
                default   => 'Steady natural cinematic walking velocity',
            };

            $lensPhrase = match ($lensStyle) {
                'wide_16mm'  => 'Shot on 16mm ultra-wide rectilinear architectural lens, zero optical distortion, straight vertical lines',
                'macro_50mm' => 'Shot on 50mm f/2.0 portrait lens, gentle background bokeh emphasizing tactile textures',
                default      => 'Shot on Arri Alexa Mini, 35mm anamorphic lens, 24fps film reel',
            };

            $geometryRule = $strictGeometry
                ? '100% rigid architectural geometry, straight vertical perspective lines, fluid motion without warping or morphing.'
                : 'Fluid cinematic motion without morphing.';

            return "Cinematic 4K architectural video. {$motionPhrase} through {$subject} {$projName}. {$cutKeywords}. Duration: {$duration} continuous uncut take. Pacing: {$pacePhrase}. Lighting & Atmosphere: {$moodPhrase}. {$matPhrase} {$lensPhrase}, {$geometryRule}";
        }

        $mandatoryPrefix = 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Transform this 3D model viewport into a magazine-grade, real-life architectural photograph. Maintain 100% of the exact geometry, camera perspective, section-cut lines, and color palette of every surface, but replace all untextured 3D polygons with authentic physical real-world materials: genuine wood grain, authentic concrete pores, clear glass reflections, and smooth painted walls.';

        return "{$mandatoryPrefix} Architectural space of {$subject} {$projName}. {$cutKeywords}. Camera perspective: {$angleKeywords}. Lighting: {$lightingKeywords}. {$matPhrase} Masterpiece 8k architectural photography, shot on Hasselblad H6D-100c, 35mm lens, f/8, realistic physical textures, natural soft illumination, ambient occlusion crevices, Architectural Digest magazine quality photograph.";
    }
}
