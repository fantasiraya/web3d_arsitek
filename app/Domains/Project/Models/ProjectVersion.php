<?php

namespace App\Domains\Project\Models;

use Database\Factories\ProjectVersionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $project_id
 * @property int $version_number
 * @property string $file_path
 * @property int $file_size_bytes
 * @property string|null $changelog
 * @property bool $is_draco_compressed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectVersion extends Model
{
    /** @use HasFactory<ProjectVersionFactory> */
    use HasFactory, HasUuids;

    protected $table = 'project_versions';

    protected $fillable = [
        'project_id',
        'version_number',
        'file_path',
        'file_size_bytes',
        'changelog',
        'is_draco_compressed',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ProjectVersion $version): void {
            $disk = Storage::disk('public');
            $path = $version->file_path;

            if (empty($path)) {
                return;
            }

            // Delete the tracked file_path (may be the draco-compressed version)
            if ($disk->exists($path)) {
                $deleted = $disk->delete($path);
                if (!$deleted) {
                    Log::error("[ProjectVersion] Failed to delete file: {$path} (version {$version->id})");
                }
            }

            // Also delete the original uncompressed GLB if it exists alongside the draco file.
            // DracoCompressionJob renames   filename.glb → filename_draco.glb  and updates
            // file_path to the _draco variant, but leaves the original on disk uncommented.
            if (str_ends_with($path, '_draco.glb')) {
                $original = str_replace('_draco.glb', '.glb', $path);
                if ($disk->exists($original)) {
                    $disk->delete($original);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'is_draco_compressed' => 'boolean',
        ];
    }

    /**
     * The parent project.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
