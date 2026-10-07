<?php

namespace App\Domains\Rab\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $rab_price_item_id
 * @property string      $component_type  tenaga | bahan | peralatan
 * @property string      $name
 * @property string|null $code
 * @property string      $unit
 * @property float       $coefficient
 * @property float       $unit_price
 * @property float       $amount          = coefficient × unit_price
 * @property int         $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabPriceItemComponent extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_TENAGA     = 'tenaga';
    public const TYPE_BAHAN      = 'bahan';
    public const TYPE_PERALATAN  = 'peralatan';

    public const TYPES = [
        self::TYPE_TENAGA,
        self::TYPE_BAHAN,
        self::TYPE_PERALATAN,
    ];

    protected $table = 'rab_price_item_components';

    protected $fillable = [
        'rab_price_item_id',
        'component_type',
        'name',
        'code',
        'unit',
        'coefficient',
        'unit_price',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'coefficient' => 'float',
            'unit_price'  => 'float',
            'amount'      => 'float',
            'sort_order'  => 'integer',
        ];
    }

    /** Hitung amount dari coefficient × unit_price. */
    public function calculateAmount(): float
    {
        return round($this->coefficient * $this->unit_price, 2);
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(RabPriceItem::class, 'rab_price_item_id');
    }
}
