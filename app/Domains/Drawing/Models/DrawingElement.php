<?php

namespace App\Domains\Drawing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Elemen Vektor Kanvas 2D (garis, dinding, pintu, jendela, teks, dimensi, dll).
 */
class DrawingElement extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'drawing_elements';

    protected $fillable = [
        'drawing_sheet_id',
        'type',
        'data',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'data'       => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(DrawingSheet::class, 'drawing_sheet_id');
    }
}

