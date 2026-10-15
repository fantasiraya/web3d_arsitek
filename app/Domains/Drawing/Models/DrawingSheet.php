<?php

namespace App\Domains\Drawing\Models;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Model Lembar Kerja Gambar 2D Teknik (Denah, Tampak, Potongan).
 */
class DrawingSheet extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_PLAN = 'plan';
    public const TYPE_ELEVATION = 'elevation';
    public const TYPE_SECTION = 'section';

    public const TYPES = [
        self::TYPE_PLAN,
        self::TYPE_ELEVATION,
        self::TYPE_SECTION,
    ];

    protected $table = 'drawing_sheets';

    protected $fillable = [
        'project_id',
        'user_id',
        'title',
        'type',
        'scale',
        'paper_size',
        'orientation',
        'width',
        'height',
        'grid_spacing',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'width'        => 'integer',
            'height'       => 'integer',
            'grid_spacing' => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(DrawingElement::class, 'drawing_sheet_id')->orderBy('sort_order');
    }
}

