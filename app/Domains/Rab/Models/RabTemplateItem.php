<?php

namespace App\Domains\Rab\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $rab_template_id
 * @property string      $rab_price_item_id
 * @property string      $section
 * @property int         $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabTemplateItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rab_template_items';

    protected $fillable = [
        'rab_template_id',
        'rab_price_item_id',
        'section',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RabTemplate::class, 'rab_template_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(RabPriceItem::class, 'rab_price_item_id');
    }
}
