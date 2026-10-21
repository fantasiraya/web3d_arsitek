<?php

namespace App\Domains\Project\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Comment\Models\Comment;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $file_path
 * @property int $file_size_bytes
 * @property bool $is_draco_compressed
 * @property string $share_token
 * @property int $max_revisions_allowed
 * @property int $current_revision_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';

    protected $table = 'projects';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'client_name',
        'client_address',
        'file_path',
        'file_size_bytes',
        'is_draco_compressed',
        'share_token',
        'max_revisions_allowed',
        'current_revision_count',
        'latitude',
        'longitude',
        'north_angle',
        'solar_date',
    ];

    protected function casts(): array
    {
        return [
            'latitude'               => 'float',
            'longitude'              => 'float',
            'north_angle'            => 'float',
            'solar_date'             => 'date:Y-m-d',
            'max_revisions_allowed'  => 'integer',
            'current_revision_count' => 'integer',
            'file_size_bytes'        => 'integer',
            'is_draco_compressed'    => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            $disk = Storage::disk('public');

            // 1. Explicitly delete each ProjectVersion file via Eloquent
            //    so ProjectVersion::deleting hook fires for every version.
            $project->versions()->each(fn (ProjectVersion $v) => $v->delete());

            // 2. Explicitly delete each ProjectCapture file via Eloquent
            $project->captures()->each(fn (ProjectCapture $c) => $c->delete());

            // 3. Delete the project-level file_path (and its draco variant if present)
            $path = $project->file_path;
            if (!empty($path) && $disk->exists($path)) {
                $deleted = $disk->delete($path);
                if (!$deleted) {
                    Log::error("[Project] Failed to delete project file: {$path} (project {$project->id})");
                }
            }

            if (!empty($path) && !str_ends_with($path, '_draco.glb')) {
                $dracoPath = preg_replace('/\.gl[bt]f?$/i', '_draco.glb', $path);
                if ($dracoPath && $disk->exists($dracoPath)) {
                    $disk->delete($dracoPath);
                }
            }

            // 4. Delete the entire project folder projects/{id}/
            //    This catches all captures, versions, and orphaned subfiles.
            $projectFolder = "projects/{$project->id}";
            $disk->deleteDirectory($projectFolder);
        });
    }

    /**
     * The Architect (owner) of the project.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias for user() - project owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->user();
    }

    /**
     * Revision versions of this 3D model project.
     *
     * @return HasMany<ProjectVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ProjectVersion::class, 'project_id')->orderBy('version_number', 'asc');
    }

    /**
     * Clients invited to review this project.
     *
     * @return HasMany<ProjectClient, $this>
     */
    public function invitedClients(): HasMany
    {
        return $this->hasMany(ProjectClient::class, 'project_id');
    }

    /**
     * Spatial pin comments on this project.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'project_id');
    }

    /**
     * Real-time chat messages for this project.
     *
     * @return HasMany<ChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'project_id')->orderBy('created_at', 'asc');
    }

    /**
     * 3D Snapshot captures on this project.
     *
     * @return HasMany<ProjectCapture, $this>
     */
    public function captures(): HasMany
    {
        return $this->hasMany(ProjectCapture::class, 'project_id')->latest();
    }

    /**
     * Saved camera presets on this project.
     *
     * @return HasMany<ProjectCameraPreset, $this>
     */
    public function cameraPresets(): HasMany
    {
        return $this->hasMany(ProjectCameraPreset::class, 'project_id')->orderBy('sort_order', 'asc');
    }

    /**
     * Drawing sheets belonging to this project.
     *
     * @return HasMany<\App\Domains\Drawing\Models\DrawingSheet, $this>
     */
    public function drawingSheets(): HasMany
    {
        return $this->hasMany(\App\Domains\Drawing\Models\DrawingSheet::class, 'project_id')->orderBy('sort_order', 'asc');
    }


    /**
     * Check if project revision limit has been reached or exceeded.
     */
    public function hasReachedRevisionLimit(): bool
    {
        return $this->current_revision_count >= $this->max_revisions_allowed;
    }

    public function getNameAttribute(): string
    {
        return $this->title;
    }

    public function getStatusAttribute(): string
    {
        return self::STATUS_DRAFT;
    }
}
