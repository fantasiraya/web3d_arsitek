<?php

namespace App\Http\Controllers\Admin;

use App\Domains\SystemConfig\Models\SystemSetting;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use App\Http\Controllers\Controller;
use App\Support\CacheKeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingController extends Controller
{
    public function __construct(
        protected SystemSettingRepository $settingsRepo
    ) {}

    public function edit(): Response
    {
        $dbSettings = SystemSetting::all()->keyBy('key');

        $dbProvider = $dbSettings->get('vision_ai_provider')?->value;
        $activeProvider = strtolower($dbProvider ?: config('services.vision_ai.provider', 'gemini'));

        $providers = [
            'gemini' => [
                'name'            => 'Google Gemini',
                'description'     => 'Model multimodal tercepat & akurat dari Google (Disarankan: Gemini 2.5 Flash)',
                'has_db_key'      => !empty($dbSettings->get('gemini_api_key')?->value),
                'has_env_key'     => !empty(config('services.vision_ai.providers.gemini.api_key') ?: config('services.gemini.api_key')),
                'masked_key'      => $this->maskSecret($dbSettings->get('gemini_api_key')?->value ?? config('services.vision_ai.providers.gemini.api_key') ?? config('services.gemini.api_key')),
                'model'           => $dbSettings->get('gemini_model')?->value ?? config('services.vision_ai.providers.gemini.model') ?? config('services.gemini.model', 'gemini-2.5-flash'),
                'is_model_from_db'=> !empty($dbSettings->get('gemini_model')?->value),
                'default_model'   => 'gemini-3.5-flash',
                'recommended_models' => ['gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-3.1-flash-lite'],
            ],
            'openai' => [
                'name'            => 'OpenAI (ChatGPT)',
                'description'     => 'Model Vision OpenAI yang fleksibel (Disarankan: GPT-4o Mini)',
                'has_db_key'      => !empty($dbSettings->get('openai_api_key')?->value),
                'has_env_key'     => !empty(config('services.vision_ai.providers.openai.api_key')),
                'masked_key'      => $this->maskSecret($dbSettings->get('openai_api_key')?->value ?? config('services.vision_ai.providers.openai.api_key')),
                'model'           => $dbSettings->get('openai_model')?->value ?? config('services.vision_ai.providers.openai.model', 'gpt-4o-mini'),
                'is_model_from_db'=> !empty($dbSettings->get('openai_model')?->value),
                'base_url'        => $dbSettings->get('openai_base_url')?->value ?? config('services.vision_ai.providers.openai.base_url', 'https://api.openai.com/v1'),
                'default_model'   => 'gpt-4o-mini',
                'recommended_models' => ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'],
            ],
            'claude' => [
                'name'            => 'Anthropic Claude',
                'description'     => 'Model kecerdasan tinggi dari Anthropic (Disarankan: Claude 3.5 Sonnet)',
                'has_db_key'      => !empty($dbSettings->get('claude_api_key')?->value),
                'has_env_key'     => !empty(config('services.vision_ai.providers.claude.api_key')),
                'masked_key'      => $this->maskSecret($dbSettings->get('claude_api_key')?->value ?? config('services.vision_ai.providers.claude.api_key')),
                'model'           => $dbSettings->get('claude_model')?->value ?? config('services.vision_ai.providers.claude.model', 'claude-3-5-sonnet-20241022'),
                'is_model_from_db'=> !empty($dbSettings->get('claude_model')?->value),
                'default_model'   => 'claude-3-5-sonnet-20241022',
                'recommended_models' => ['claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307'],
            ],
            'ollama' => [
                'name'            => 'Ollama (Self-Hosted / Local)',
                'description'     => 'Model open-source di server lokal tanpa biaya token cloud (Disarankan: LLaVA)',
                'has_db_key'      => true, // Local instance tidak wajib API key
                'has_env_key'     => true,
                'masked_key'      => 'No key required (Local)',
                'model'           => $dbSettings->get('ollama_model')?->value ?? config('services.vision_ai.providers.ollama.model', 'llava'),
                'is_model_from_db'=> !empty($dbSettings->get('ollama_model')?->value),
                'base_url'        => $dbSettings->get('ollama_base_url')?->value ?? config('services.vision_ai.providers.ollama.base_url', 'http://localhost:11434'),
                'default_model'   => 'llava',
                'recommended_models' => ['llava', 'llava-llama3', 'bakllava'],
            ],
        ];

        return Inertia::render('admin/settings/Ai', [
            'activeProvider'     => $activeProvider,
            'isProviderFromDb'   => !empty($dbProvider),
            'providers'          => $providers,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'vision_ai_provider' => ['required', 'string', 'in:gemini,openai,claude,ollama'],
            'gemini_api_key'     => ['nullable', 'string', 'max:500'],
            'gemini_model'       => ['nullable', 'string', 'max:100'],
            'openai_api_key'     => ['nullable', 'string', 'max:500'],
            'openai_model'       => ['nullable', 'string', 'max:100'],
            'openai_base_url'    => ['nullable', 'string', 'max:255'],
            'claude_api_key'     => ['nullable', 'string', 'max:500'],
            'claude_model'       => ['nullable', 'string', 'max:100'],
            'ollama_model'       => ['nullable', 'string', 'max:100'],
            'ollama_base_url'    => ['nullable', 'string', 'max:255'],
            'reset_keys'         => ['nullable', 'array'],
            'reset_keys.*'       => ['string'],
        ]);

        // Key-key API secret yang tidak boleh di-overwrite jika dikirim kosong atau berisi tanda bintang '****'
        $secretKeys = ['gemini_api_key', 'openai_api_key', 'claude_api_key'];

        // 1. Tangani Reset Keys (menghapus dari database agar kembali memakai fallback .env)
        $resetKeys = $validated['reset_keys'] ?? [];
        if (!empty($resetKeys)) {
            SystemSetting::whereIn('key', $resetKeys)->delete();
        }

        // 2. Simpan Provider Aktif
        SystemSetting::updateOrCreate(
            ['key' => 'vision_ai_provider'],
            [
                'value'       => strtolower($validated['vision_ai_provider']),
                'type'        => 'string',
                'description' => 'Provider aktif untuk Vision AI prompt generation',
            ]
        );

        // 3. Simpan setting lainnya
        $otherKeys = [
            'gemini_api_key', 'gemini_model',
            'openai_api_key', 'openai_model', 'openai_base_url',
            'claude_api_key', 'claude_model',
            'ollama_model', 'ollama_base_url',
        ];

        foreach ($otherKeys as $k) {
            if (in_array($k, $resetKeys, true)) {
                continue; // Sudah di-delete
            }

            if (!array_key_exists($k, $validated)) {
                continue;
            }

            $val = trim((string) ($validated[$k] ?? ''));

            // Abaikan secret keys yang tidak diisi atau masih berupa masked string
            if (in_array($k, $secretKeys, true)) {
                if (empty($val) || $this->isMasked($val)) {
                    continue;
                }
            }

            if ($val !== '') {
                SystemSetting::updateOrCreate(
                    ['key' => $k],
                    [
                        'value'       => $val,
                        'type'        => 'string',
                        'description' => "Pengaturan Vision AI: {$k}",
                    ]
                );
            }
        }

        // Pastikan cache settings di-flush
        CacheKeys::flushSettings();

        return back()->with('success', 'Pengaturan Vision AI berhasil disimpan ke database.');
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:gemini,openai,claude,ollama'],
            'api_key'  => ['nullable', 'string'],
            'model'    => ['nullable', 'string'],
            'base_url' => ['nullable', 'string'],
        ]);

        $provider = $validated['provider'];
        $dbSettings = SystemSetting::all()->keyBy('key');

        // Resolusi API Key & Model (prioritas: nilai input jika tidak masked -> DB -> .env)
        $inputKey = trim((string) ($validated['api_key'] ?? ''));
        if ($this->isMasked($inputKey)) {
            $inputKey = '';
        }

        try {
            if ($provider === 'gemini') {
                $apiKey = $inputKey ?: ($dbSettings->get('gemini_api_key')?->value ?? config('services.vision_ai.providers.gemini.api_key') ?? config('services.gemini.api_key'));
                $model = trim((string) ($validated['model'] ?? '')) ?: ($dbSettings->get('gemini_model')?->value ?? config('services.vision_ai.providers.gemini.model') ?? 'gemini-2.5-flash');

                if (empty($apiKey)) {
                    return response()->json(['success' => false, 'message' => 'API Key Gemini belum disetel.'], 422);
                }

                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}?key={$apiKey}";
                $response = Http::timeout(10)->get($url);

                if ($response->successful()) {
                    $displayName = $response->json('displayName') ?? $model;
                    return response()->json([
                        'success' => true,
                        'message' => "Koneksi Google Gemini sukses! Model '{$displayName}' siap digunakan.",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal verifikasi Gemini: ' . ($response->json('error.message') ?? $response->body()),
                ], 400);
            }

            if ($provider === 'openai') {
                $apiKey = $inputKey ?: ($dbSettings->get('openai_api_key')?->value ?? config('services.vision_ai.providers.openai.api_key'));
                $baseUrl = trim((string) ($validated['base_url'] ?? '')) ?: ($dbSettings->get('openai_base_url')?->value ?? config('services.vision_ai.providers.openai.base_url', 'https://api.openai.com/v1'));
                $model = trim((string) ($validated['model'] ?? '')) ?: ($dbSettings->get('openai_model')?->value ?? config('services.vision_ai.providers.openai.model', 'gpt-4o-mini'));

                if (empty($apiKey)) {
                    return response()->json(['success' => false, 'message' => 'API Key OpenAI belum disetel.'], 422);
                }

                $url = rtrim($baseUrl, '/') . "/models/{$model}";
                $response = Http::withToken($apiKey)->timeout(10)->get($url);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => "Koneksi OpenAI sukses! Model '{$model}' terverifikasi aktif.",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal verifikasi OpenAI: ' . ($response->json('error.message') ?? $response->body()),
                ], 400);
            }

            if ($provider === 'claude') {
                $apiKey = $inputKey ?: ($dbSettings->get('claude_api_key')?->value ?? config('services.vision_ai.providers.claude.api_key'));
                $model = trim((string) ($validated['model'] ?? '')) ?: ($dbSettings->get('claude_model')?->value ?? config('services.vision_ai.providers.claude.model', 'claude-3-5-sonnet-20241022'));

                if (empty($apiKey)) {
                    return response()->json(['success' => false, 'message' => 'API Key Anthropic Claude belum disetel.'], 422);
                }

                $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $model,
                    'max_tokens' => 1,
                    'messages'   => [
                        ['role' => 'user', 'content' => 'Hi'],
                    ],
                ]);

                if ($response->successful()) {
                    return response()->json([
                        'success' => true,
                        'message' => "Koneksi Claude sukses! Model '{$model}' terverifikasi aktif.",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal verifikasi Claude: ' . ($response->json('error.message') ?? $response->body()),
                ], 400);
            }

            if ($provider === 'ollama') {
                $baseUrl = trim((string) ($validated['base_url'] ?? '')) ?: ($dbSettings->get('ollama_base_url')?->value ?? config('services.vision_ai.providers.ollama.base_url', 'http://localhost:11434'));
                $model = trim((string) ($validated['model'] ?? '')) ?: ($dbSettings->get('ollama_model')?->value ?? config('services.vision_ai.providers.ollama.model', 'llava'));

                $url = rtrim($baseUrl, '/') . '/api/tags';
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $models = collect($response->json('models', []))->pluck('name')->toArray();
                    $hasModel = in_array($model, $models, true) || in_array("{$model}:latest", $models, true);

                    $modelNotice = $hasModel
                        ? "Model '{$model}' ditemukan pada server Ollama."
                        : "Server Ollama aktif, namun model '{$model}' belum terunduh (jalankan 'ollama pull {$model}').";

                    return response()->json([
                        'success' => true,
                        'message' => "Koneksi Ollama sukses! {$modelNotice}",
                    ]);
                }

                return response()->json([
                    'success' => false,
                    'message' => "Gagal menghubungi server Ollama di '{$baseUrl}'. Pastikan server lokal sedang berjalan.",
                ], 400);
            }
        } catch (\Throwable $e) {
            Log::warning("AI test connection error [{$provider}]: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menguji koneksi: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json(['success' => false, 'message' => 'Provider tidak valid.'], 400);
    }

    protected function maskSecret(?string $key): string
    {
        if (empty($key)) {
            return '';
        }

        $length = strlen($key);
        if ($length <= 8) {
            return '********';
        }

        return substr($key, 0, 4) . '••••••••' . substr($key, -4);
    }

    protected function isMasked(string $value): bool
    {
        return str_contains($value, '••••') || str_contains($value, '****') || str_contains($value, '••') || str_contains($value, '**');
    }
}

