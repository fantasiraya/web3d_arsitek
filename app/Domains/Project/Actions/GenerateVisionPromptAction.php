<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GenerateVisionPromptAction
{
    public function execute(?User $user, Project $project, array $data): array
    {
        $metadata = $data['scene_metadata'] ?? [];
        $rawImage = $data['image'] ?? '';

        $fallbackPrompt = $this->buildFallbackPrompt($project, $metadata);

        $apiKey = config('services.gemini.api_key');
        if (empty($apiKey)) {
            return [
                'prompt' => $fallbackPrompt,
                'source' => 'local-spatial-3d',
                'notice' => 'GEMINI_API_KEY belum disetel di file .env. Menggunakan generator spasial 3D internal.',
            ];
        }

        if (empty($rawImage)) {
            return [
                'prompt' => $fallbackPrompt,
                'source' => 'local-spatial-3d',
                'notice' => 'Gambar kanvas tidak tersedia. Menggunakan generator spasial 3D.',
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

            $model = config('services.gemini.model', 'gemini-2.5-flash');
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

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
                $candidateText = $response->json('candidates.0.content.parts.0.text');
                if (!empty($candidateText)) {
                    $cleanedText = trim($candidateText);
                    // Hapus tanda petik awal/akhir atau backticks jika ada
                    $cleanedText = preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $cleanedText);
                    $cleanedText = trim($cleanedText);
                    $cleanedText = preg_replace('/^"|"$/', '', $cleanedText);
                    $cleanedText = trim($cleanedText);

                    // Pastikan kalimat pembuka wajib selalu ada di awal prompt
                    $mandatoryPrefix = 'Strict image-to-image architectural photorealistic rendering of the attached 3D reference image. Maintain 100% of the exact geometry, camera perspective, section-cut lines, AND THE EXACT COLOR PALETTE of every surface as shown in the reference image.';
                    if (!str_starts_with($cleanedText, 'Strict image-to-image')) {
                        $cleanedText = $mandatoryPrefix . ' ' . $cleanedText;
                    }

                    return [
                        'prompt' => $cleanedText,
                        'source' => 'gemini-vision',
                        'model'  => $model,
                    ];
                }
            }

            Log::warning('Gemini Vision API returned unexpected response', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 500),
            ]);

            return [
                'prompt' => $fallbackPrompt,
                'source' => 'local-spatial-3d',
                'notice' => 'Layanan Gemini Vision sedang sibuk. Menggunakan generator spasial 3D internal.',
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini Vision API exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'prompt' => $fallbackPrompt,
                'source' => 'local-spatial-3d',
                'notice' => 'Gagal menghubungi Gemini Vision. Menggunakan generator spasial 3D internal.',
            ];
        }
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
