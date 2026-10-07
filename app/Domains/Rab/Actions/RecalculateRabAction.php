<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya sumber kebenaran untuk kalkulasi total RAB.
 * Setiap perubahan item WAJIB memanggil action ini.
 *
 * Formula:
 *   item.subtotal  = qty × (1 + waste%) × unit_price
 *   subtotal_doc   = Σ item.subtotal
 *   overhead_amount = subtotal_doc × (overhead_percent / 100)
 *   ppn_amount      = (subtotal_doc + overhead_amount) × (ppn_percent / 100)
 *   total           = subtotal_doc + overhead_amount + ppn_amount
 */
class RecalculateRabAction
{
    public function execute(RabDocument $document): RabDocument
    {
        return DB::transaction(function () use ($document) {
            // 1. Recalculate setiap item dan simpan subtotal-nya
            $items = $document->items()->get();

            foreach ($items as $item) {
                $itemSubtotal = round(
                    $item->quantity * (1 + $item->waste_percent / 100) * $item->unit_price,
                    2
                );
                $item->subtotal = $itemSubtotal;
                $item->saveQuietly();
            }

            // 2. Hitung totals dokumen
            $subtotal       = $items->sum('subtotal');
            $overheadAmount = round($subtotal * ($document->overhead_percent / 100), 2);
            $ppnAmount      = round(($subtotal + $overheadAmount) * ($document->ppn_percent / 100), 2);
            $total          = round($subtotal + $overheadAmount + $ppnAmount, 2);

            $document->subtotal        = $subtotal;
            $document->overhead_amount = $overheadAmount;
            $document->ppn_amount      = $ppnAmount;
            $document->total           = $total;
            $document->save();

            return $document->fresh();
        });
    }
}
