<?php

namespace App\Domains\Rab\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $rab_document_id
 * @property string|null $rab_price_item_id
 * @property string      $section
 * @property string      $description
 * @property string      $unit
 * @property float       $quantity
 * @property float       $waste_percent
 * @property float       $unit_price      snapshot harga saat item dibuat
 * @property float       $subtotal        = qty × (1 + waste%) × unit_price
 * @property string|null $source_ref
 * @property bool        $is_mapped
 * @property bool        $is_estimate
 * @property int         $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rab_items';

    protected $fillable = [
        'rab_document_id',
        'rab_price_item_id',
        'section',
        'description',
        'unit',
        'quantity',
        'waste_percent',
        'unit_price',
        'subtotal',
        'source_ref',
        'is_mapped',
        'is_estimate',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'float',
            'waste_percent' => 'float',
            'unit_price'    => 'float',
            'subtotal'      => 'float',
            'is_mapped'     => 'boolean',
            'is_estimate'   => 'boolean',
            'sort_order'    => 'integer',
        ];
    }

    /**
     * Hitung subtotal: qty × (1 + waste%) × unit_price.
     * Dipakai RecalculateRabAction sebagai sumber kebenaran.
     */
    public function calculateSubtotal(): float
    {
        return round($this->quantity * (1 + $this->waste_percent / 100) * $this->unit_price, 2);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(RabDocument::class, 'rab_document_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(RabPriceItem::class, 'rab_price_item_id');
    }
}
