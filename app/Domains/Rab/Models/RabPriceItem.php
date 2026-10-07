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
 * @property float       $unit_price      Hasil akhir: bisa manual atau dihitung dari komponen
 * @property string|null $category
 * @property float       $overhead_percent  Overhead & Profit level analisa (0-100)
 * @property bool        $has_components    True jika unit_price dihitung dari komponen AHSP
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
        'overhead_percent',
        'has_components',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'       => 'float',
            'overhead_percent' => 'float',
            'has_components'   => 'boolean',
        ];
    }

    /** Pemilik harga satuan. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Komponen AHSP (tenaga, bahan, peralatan). */
    public function components(): HasMany
    {
        return $this->hasMany(RabPriceItemComponent::class, 'rab_price_item_id')
            ->orderBy('component_type')
            ->orderBy('sort_order');
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

    /**
     * Hitung unit_price dari komponen AHSP + overhead.
     * Dipanggil oleh RecalculatePriceItemAction.
     *
     * Formula:
     *   base = Σ component.amount
     *   overhead_amount = base × (overhead_percent / 100)
     *   unit_price = base + overhead_amount
     */
    public function computeUnitPrice(): float
    {
        if (! $this->has_components) {
            return $this->unit_price; // manual — tidak dihitung ulang
        }

        $components = $this->components()->get();
        $base       = $components->sum('amount');
        $overhead   = round($base * ($this->overhead_percent / 100), 2);

        return round($base + $overhead, 2);
    }
}
