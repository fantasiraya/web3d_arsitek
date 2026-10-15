<?php

namespace App\Domains\VisionStudio\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $title
 * @property string $room_name
 * @property string $prompt_type
 * @property string $file_path
 * @property string|null $end_file_path
 * @property int $file_size_bytes
 * @property int $width
 * @property int $height
 * @property string|null $prompt
 * @property string|null $negative_prompt
 * @property array|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VisionCreation extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'vision_creations';

    protected $fillable = [
        'user_id',
        'title',
        'room_name',
        'prompt_type',
        'file_path',
        'end_file_path',
        'file_size_bytes',
        'width',
        'height',
        'prompt',
        'negative_prompt',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'width'           => 'integer',
            'height'          => 'integer',
            'metadata'        => 'array',
        ];
    }

    protected $appends = [
        'url',
        'end_url',
    ];

    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        return '/storage/' . ltrim($this->file_path, '/');
    }

    public function getEndUrlAttribute(): ?string
    {
        if (! $this->end_file_path) {
            return null;
        }

        if (str_starts_with($this->end_file_path, 'http://') || str_starts_with($this->end_file_path, 'https://')) {
            return $this->end_file_path;
        }

        return '/storage/' . ltrim($this->end_file_path, '/');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::deleted(function (VisionCreation $creation): void {
            if ($creation->file_path && Storage::disk('public')->exists($creation->file_path)) {
                Storage::disk('public')->delete($creation->file_path);
            }
            if ($creation->end_file_path && Storage::disk('public')->exists($creation->end_file_path)) {
                Storage::disk('public')->delete($creation->end_file_path);
            }
        });
    }
}

