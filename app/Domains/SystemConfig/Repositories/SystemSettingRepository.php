<?php

namespace App\Domains\SystemConfig\Repositories;

use App\Domains\SystemConfig\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SystemSettingRepository
{
    public const CACHE_KEY = 'system_settings_all';

    /**
     * Retrieve all system settings from cache, or DB if not cached.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return SystemSetting::all()
                ->keyBy('key')
                ->map(fn (SystemSetting $setting) => $setting->getTypedValue())
                ->toArray();
        });
    }

    /**
     * Get a specific setting by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    /**
     * Set or update a setting.
     */
    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): SystemSetting
    {
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type' => is_array($value) ? 'json' : $type,
                'description' => $description,
            ]
        );

        return $setting;
    }

    /**
     * Clear the settings cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
