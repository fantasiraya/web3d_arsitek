<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabPriceItem;
use App\Domains\Rab\Models\RabPriceItemComponent;
use Illuminate\Support\Facades\DB;

/**
 * Kalkulasi ulang unit_price sebuah harga satuan dari komponen AHSP-nya.
 *
 * Formula (sesuai standar SNI AHSP):
 *   base            = Σ (coefficient × unit_price) per komponen
 *   overhead_amount = base × (overhead_percent / 100)
 *   unit_price      = base + overhead_amount
 *
 * Setelah unit_price di-update, ini TIDAK mengubah rab_items.unit_price
 * yang sudah ada (snapshot tetap terlindungi).
 */
class RecalculatePriceItemAction
{
    /**
     * Hitung ulang amount tiap komponen + update unit_price harga satuan.
     */
    public function execute(RabPriceItem $priceItem): RabPriceItem
    {
        if (! $priceItem->has_components) {
            return $priceItem; // mode manual — tidak perlu recalculate
        }

        return DB::transaction(function () use ($priceItem) {
            // 1. Hitung ulang amount tiap komponen
            $components = $priceItem->components()->get();
            $base       = 0.0;

            foreach ($components as $comp) {
                $amount = round($comp->coefficient * $comp->unit_price, 2);
                $comp->amount = $amount;
                $comp->saveQuietly();
                $base += $amount;
            }

            // 2. Hitung overhead + final unit_price
            $overhead  = round($base * ($priceItem->overhead_percent / 100), 2);
            $unitPrice = round($base + $overhead, 2);

            $priceItem->unit_price = $unitPrice;
            $priceItem->save();

            return $priceItem->fresh();
        });
    }

    /**
     * Simpan atau update satu komponen lalu trigger recalculate.
     */
    public function saveComponent(
        RabPriceItem $priceItem,
        array $data,
        ?RabPriceItemComponent $existing = null
    ): RabPriceItemComponent {
        $amount = round((float) $data['coefficient'] * (float) $data['unit_price'], 2);

        if ($existing === null) {
            $maxOrder = $priceItem->components()
                ->where('component_type', $data['component_type'])
                ->max('sort_order') ?? -1;

            $component = RabPriceItemComponent::create([
                'rab_price_item_id' => $priceItem->id,
                'component_type'    => $data['component_type'],
                'name'              => $data['name'],
                'code'              => $data['code'] ?? null,
                'unit'              => $data['unit'],
                'coefficient'       => $data['coefficient'],
                'unit_price'        => $data['unit_price'],
                'amount'            => $amount,
                'sort_order'        => $data['sort_order'] ?? ($maxOrder + 1),
            ]);
        } else {
            $existing->fill([
                'component_type' => $data['component_type'],
                'name'           => $data['name'],
                'code'           => $data['code'] ?? $existing->code,
                'unit'           => $data['unit'],
                'coefficient'    => $data['coefficient'],
                'unit_price'     => $data['unit_price'],
                'amount'         => $amount,
                'sort_order'     => $data['sort_order'] ?? $existing->sort_order,
            ]);
            $existing->save();
            $component = $existing;
        }

        // Set has_components = true dan recalculate
        if (! $priceItem->has_components) {
            $priceItem->has_components = true;
            $priceItem->save();
        }

        $this->execute($priceItem);

        return $component->fresh();
    }
}
