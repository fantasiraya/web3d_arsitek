<?php

namespace App\Domains\Project\Models;

use Database\Factories\ProjectVersionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
