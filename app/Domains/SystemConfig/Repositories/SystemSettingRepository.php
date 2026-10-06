<?php

namespace App\Domains\SystemConfig\Repositories;

use App\Domains\SystemConfig\Models\SystemSetting;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class SystemSettingRepository
{
    /**
     * Retrieve all system settings — cached selamanya dengan tag 'system_settings'.
     * Invalidasi via CacheKeys::flushSettings() setiap kali admin mengubah setting.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::tags([CacheKeys::TAG_SETTINGS])
            ->rememberForever(
                CacheKeys::KEY_SETTINGS_ALL,
                fn () => SystemSetting::all()
                    ->keyBy('key')
                    ->map(fn (SystemSetting $s) => $s->getTypedValue())
                    ->toArray()
            );
    }

    /**
     * Get a specific setting by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /**
     * Get a boolean setting — toleran terhadap true/false/1/0/"1"/"0".
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        if (is_bool($value))   return $value;
        if (is_int($value))    return $value !== 0;
        if (is_string($value)) return in_array($value, ['1', 'true', 'yes'], true);

        return (bool) $value;
    }

    /**
     * Set or update a setting, kemudian flush cache settings.
     */
    public function set(string $key, mixed $value, string $type = 'string', ?string $description = null): SystemSetting
    {
        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            [
                'value'       => is_array($value) ? json_encode($value) : (string) $value,
                'type'        => is_array($value) ? 'json' : $type,
                'description' => $description,
            ]
        );

        // Flush tag — semua key di bawah tag 'system_settings' ikut terhapus
        CacheKeys::flushSettings();

        return $setting;
    }

    /**
     * Clear the settings cache secara eksplisit.
     * Alias publik untuk CacheKeys::flushSettings() — dipakai di tempat lain
     * yang tidak perlu import CacheKeys secara langsung.
     */
    public function clearCache(): void
    {
        CacheKeys::flushSettings();
    }
}
