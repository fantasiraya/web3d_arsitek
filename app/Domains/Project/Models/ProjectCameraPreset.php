<?php

namespace App\Domains\Project\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $project_id
 * @property string $created_by
 * @property string $name
 * @property float  $position_x
 * @property float  $position_y
 * @property float  $position_z
 * @property float  $target_x
 * @property float  $target_y
 * @property float  $target_z
 * @property int    $sort_order
 */
class ProjectCameraPreset extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'project_camera_presets';

    public const MAX_PER_PROJECT = 8;

    protected $fillable = [
        'project_id',
        'created_by',
        'name',
        'position_x',
        'position_y',
        'position_z',
        'target_x',
        'target_y',
        'target_z',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'position_x' => 'float',
            'position_y' => 'float',
            'position_z' => 'float',
            'target_x'   => 'float',
            'target_y'   => 'float',
            'target_z'   => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
