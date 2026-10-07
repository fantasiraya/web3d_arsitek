<?php

namespace App\Domains\Rab\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $user_id
 * @property string      $match_type  name_pattern | material | exact
 * @property string      $pattern
 * @property string      $rab_price_item_id
 * @property string      $quantity_basis  area | volume | count | length
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabMapping extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rab_mappings';

    protected $fillable = [
        'user_id',
        'match_type',
        'pattern',
        'rab_price_item_id',
        'quantity_basis',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function priceItem(): BelongsTo
    {
        return $this->belongsTo(RabPriceItem::class, 'rab_price_item_id');
    }
}
