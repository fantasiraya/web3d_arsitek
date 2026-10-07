<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Models\RabPriceItem;
use Illuminate\Support\Facades\DB;

class UpsertRabItemAction
{
    public function __construct(
        protected RecalculateRabAction $recalculate
    ) {}

    /**
     * Tambah item baru atau update item existing di dokumen RAB.
     *
     * Aturan snapshot harga (Section M.4):
     *  - Saat CREATE: unit_price disalin dari rab_price_items (snapshot).
     *  - Saat UPDATE: unit_price dikirim langsung dari request (tidak mengambil ulang dari master).
     *    Mengubah harga master tidak boleh mengubah RAB yang sudah ada.
     */
    public function execute(RabDocument $document, array $data, ?RabItem $existingItem = null): RabItem
    {
        abort_if($document->isFinal(), 403, 'Dokumen RAB sudah final. Lakukan reopen untuk mengedit item.');

        return DB::transaction(function () use ($document, $data, $existingItem) {
            // Jika ada rab_price_item_id dan ini CREATE baru, ambil snapshot harga
            $unitPrice = $data['unit_price'];
            $isMapped  = true;

            if ($existingItem === null && ! empty($data['rab_price_item_id'])) {
                $priceItem = RabPriceItem::find($data['rab_price_item_id']);
                if ($priceItem) {
                    // Snapshot: ambil dari master hanya saat item pertama kali dibuat
                    $unitPrice = $priceItem->unit_price;
                    $isMapped  = true;
                }
            }

            if ($existingItem === null) {
                // Tentukan sort_order otomatis jika tidak disupply
                $maxOrder = $document->items()
                    ->where('section', $data['section'])
                    ->max('sort_order') ?? -1;

                $item = RabItem::create([
                    'rab_document_id'   => $document->id,
                    'rab_price_item_id' => $data['rab_price_item_id'] ?? null,
                    'section'           => $data['section'],
                    'description'       => $data['description'],
                    'unit'              => $data['unit'],
                    'quantity'          => $data['quantity'],
                    'waste_percent'     => $data['waste_percent'] ?? 0,
                    'unit_price'        => $unitPrice,
                    'subtotal'          => 0,
                    'is_mapped'         => $isMapped,
                    'is_estimate'       => $data['is_estimate'] ?? false,
                    'sort_order'        => $data['sort_order'] ?? ($maxOrder + 1),
                ]);
            } else {
                // Update: pakai harga dari request, bukan ambil ulang dari master
                $existingItem->fill([
                    'rab_price_item_id' => $data['rab_price_item_id'] ?? $existingItem->rab_price_item_id,
                    'section'           => $data['section'],
                    'description'       => $data['description'],
                    'unit'              => $data['unit'],
                    'quantity'          => $data['quantity'],
                    'waste_percent'     => $data['waste_percent'] ?? $existingItem->waste_percent,
                    'unit_price'        => $unitPrice,
                    'sort_order'        => $data['sort_order'] ?? $existingItem->sort_order,
                ]);
                $existingItem->save();
                $item = $existingItem;
            }

            // Recalculate seluruh dokumen setelah perubahan item
            $this->recalculate->execute($document);

            return $item->fresh();
        });
    }
}
