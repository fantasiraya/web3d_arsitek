<?php

namespace App\Domains\Rab\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $project_id
 * @property string|null $project_version_id
 * @property string      $user_id
 * @property string|null $rab_template_id
 * @property string      $title
 * @property string      $source   manual | csv | glb
 * @property string      $status   draft | final
 * @property bool        $is_visible_to_clients
 * @property float       $overhead_percent
 * @property float       $ppn_percent
 * @property float       $subtotal
 * @property float       $overhead_amount
 * @property float       $ppn_amount
 * @property float       $total
 * @property Carbon|null $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabDocument extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINAL = 'final';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CSV    = 'csv';
    public const SOURCE_GLB    = 'glb';

    protected $table = 'rab_documents';

    protected $fillable = [
        'project_id',
        'project_version_id',
        'user_id',
        'rab_template_id',
        'title',
        'source',
        'status',
        'is_visible_to_clients',
        'overhead_percent',
        'ppn_percent',
        'subtotal',
        'overhead_amount',
        'ppn_amount',
        'total',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_clients' => 'boolean',
            'overhead_percent'      => 'float',
            'ppn_percent'           => 'float',
            'subtotal'              => 'float',
            'overhead_amount'       => 'float',
            'ppn_amount'            => 'float',
            'total'                 => 'float',
            'finalized_at'          => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projectVersion(): BelongsTo
    {
        return $this->belongsTo(ProjectVersion::class, 'project_version_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RabTemplate::class, 'rab_template_id');
    }

    /** Item-item RAB di dokumen ini, diurutkan berdasarkan section + sort_order. */
    public function items(): HasMany
    {
        return $this->hasMany(RabItem::class, 'rab_document_id')
            ->orderBy('section')
            ->orderBy('sort_order');
    }
}
