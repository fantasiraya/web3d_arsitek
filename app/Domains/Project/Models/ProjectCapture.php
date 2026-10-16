<?php

namespace App\Domains\Project\Models;

use App\Domains\Auth\Models\User;
use Database\Factories\ProjectCaptureFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string|null $code
 * @property string $project_id
 * @property string $user_id
 * @property string|null $title
 * @property string $room_name
 * @property string $captured_hour
 * @property string $file_path
 * @property string|null $ai_rendered_path
 * @property string|null $drawing_sheet_id
 * @property string|null $room_element_id
 * @property int $file_size_bytes
 * @property int $width
 * @property int $height
 * @property bool $is_visible_to_clients
 * @property string|null $prompt
 * @property string|null $description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectCapture extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'project_captures';

    protected $fillable = [
        'id',
        'code',
        'project_id',
        'drawing_sheet_id',
        'room_element_id',
        'user_id',
        'title',
        'room_name',
        'captured_hour',
        'file_path',
        'ai_rendered_path',
        'file_size_bytes',
        'width',
        'height',
        'is_visible_to_clients',
        'prompt',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes'       => 'integer',
            'width'                 => 'integer',
            'height'                => 'integer',
            'is_visible_to_clients' => 'boolean',
            'sort_order'            => 'integer',
        ];
    }

    protected $appends = [
        'url',
        'ai_rendered_url',
    ];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function getAiRenderedUrlAttribute(): ?string
    {
        return $this->ai_rendered_path ? Storage::disk('public')->url($this->ai_rendered_path) : null;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function drawingSheet(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Drawing\Models\DrawingSheet::class, 'drawing_sheet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::deleted(function (ProjectCapture $capture): void {
            if ($capture->file_path && Storage::disk('public')->exists($capture->file_path)) {
                Storage::disk('public')->delete($capture->file_path);
            }
            if ($capture->ai_rendered_path && Storage::disk('public')->exists($capture->ai_rendered_path)) {
                Storage::disk('public')->delete($capture->ai_rendered_path);
            }
        });
    }

    protected static function newFactory(): ProjectCaptureFactory
    {
        return ProjectCaptureFactory::new();
    }
}

