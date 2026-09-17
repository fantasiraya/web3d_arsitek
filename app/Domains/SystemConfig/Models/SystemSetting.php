<?php

namespace App\Domains\SystemConfig\Models;

use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Database\Factories\SystemSettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $key
 * @property string $value
 * @property string $type
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SystemSetting extends Model
{
    /** @use HasFactory<SystemSettingFactory> */
    use HasFactory, HasUuids;

    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];

    /**
     * Get the typed value according to the setting's declared type.
     */
    public function getTypedValue(): mixed
    {
        return match ($this->type) {
            'integer', 'int' => (int) $this->value,
            'boolean', 'bool' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    protected static function booted(): void
    {
        $clearCache = function () {
            app(SystemSettingRepository::class)->clearCache();
        };

        static::saved($clearCache);
        static::deleted($clearCache);
    }
}
