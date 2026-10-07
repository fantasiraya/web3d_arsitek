<?php

namespace App\Domains\Rab\Models;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string      $id
 * @property string      $user_id
 * @property string|null $code
 * @property string      $name
 * @property string      $unit
 * @property float       $unit_price
 * @property string|null $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RabPriceItem extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'rab_price_items';

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'unit',
        'unit_price',
        'category',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'float',
        ];
    }

    /** Pemilik harga satuan. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Item RAB yang menggunakan harga satuan ini (snapshot). */
    public function rabItems(): HasMany
    {
        return $this->hasMany(RabItem::class, 'rab_price_item_id');
    }

    /** Template items yang mereferensikan harga satuan ini. */
    public function templateItems(): HasMany
    {
        return $this->hasMany(RabTemplateItem::class, 'rab_price_item_id');
    }
}
