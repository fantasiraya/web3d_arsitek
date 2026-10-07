<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Models\RabPriceItem;
use Illuminate\Support\Facades\DB;

/**
 * Membuat item RAB dari hasil estimasi kuantitas geometri .glb (Fase C).
 *
 * Aturan wajib (AI_INSTRUCTIONS.md Section M.6):
 *  - Semua item dari jalur ini WAJIB is_estimate = true
 *  - Berlabel "Estimasi" di UI
 *  - Tidak boleh disajikan sebagai RAB final kontrak
 *
 * Input: array hasil kalkulasi dari useGlbQuantities.ts (dikirim dari frontend)
 * Format setiap item:
 *   [
 *     'name'              => 'DINDING_BATA_15',  // nama objek dari .glb
 *     'quantity'          => 45.6,                // nilai kuantitas (luas/volume/count)
 *     'quantity_basis'    => 'area',              // area | volume | count | length
 *     'unit'              => 'm²',
 *     'section'           => 'Struktur',
 *     'rab_price_item_id' => 'uuid|null',         // null jika belum dipetakan
 *     'unit_price'        => 150000,              // snapshot; 0 jika belum dipetakan
 *   ]
 */
class ApplyGlbQuantitiesAction
{
    public function __construct(
        protected RecalculateRabAction $recalculate
    ) {}

    /**
     * Terapkan hasil estimasi .glb ke dokumen RAB.
     * Semua item dibuat dengan is_estimate = true.
     *
     * @param  User        $user
     * @param  RabDocument $document  Dokumen RAB tujuan (harus status draft)
     * @param  array       $items     Array item estimasi dari frontend
     * @param  string|null $projectVersionId  UUID versi .glb yang digunakan
     * @return RabDocument
     */
    public function execute(
        User $user,
        RabDocument $document,
        array $items,
        ?string $projectVersionId = null,
    ): RabDocument {
        abort_if($document->isFinal(), 403, 'Dokumen RAB sudah final. Lakukan reopen untuk import estimasi.');
        abort_if($document->user_id !== $user->id, 403, 'Anda bukan pemilik dokumen RAB ini.');

        return DB::transaction(function () use ($document, $items, $projectVersionId) {
            $order = $document->items()->max('sort_order') ?? -1;

            foreach ($items as $itemData) {
                $name           = trim($itemData['name'] ?? '');
                $quantity       = (float) ($itemData['quantity'] ?? 0);
                $quantityBasis  = $itemData['quantity_basis'] ?? 'area';
                $unit           = trim($itemData['unit'] ?? 'unit');
                $section        = trim($itemData['section'] ?? 'Estimasi 3D');
                $priceItemId    = $itemData['rab_price_item_id'] ?? null;
                $unitPrice      = (float) ($itemData['unit_price'] ?? 0);

                if ($name === '' || $quantity <= 0) {
                    continue; // skip item kosong
                }

                // Snapshot harga dari master jika belum diisi manual
                if ($priceItemId && $unitPrice <= 0) {
                    $priceItem = RabPriceItem::find($priceItemId);
                    if ($priceItem) {
                        $unitPrice = $priceItem->unit_price;
                        $unit      = $unit ?: $priceItem->unit;
                    }
                }

                // Deskripsi: nama objek + basis kuantitas + label estimasi
                $basisLabel = match ($quantityBasis) {
                    'area'   => 'Luas',
                    'volume' => 'Volume',
                    'count'  => 'Jumlah',
                    'length' => 'Panjang',
                    default  => 'Kuantitas',
                };
                $description = "[Estimasi] {$name} ({$basisLabel})";

                RabItem::create([
                    'rab_document_id'   => $document->id,
                    'rab_price_item_id' => $priceItemId ?: null,
                    'section'           => $section,
                    'description'       => $description,
                    'unit'              => $unit,
                    'quantity'          => $quantity,
                    'waste_percent'     => 0,
                    'unit_price'        => $unitPrice, // snapshot
                    'subtotal'          => 0,
                    'source_ref'        => $name,      // nama objek .glb asli
                    'is_mapped'         => $priceItemId !== null,
                    'is_estimate'       => true,        // WAJIB sesuai Section M.6
                    'sort_order'        => ++$order,
                ]);
            }

            // Update source ke 'glb' dan versi yang digunakan
            $document->source = RabDocument::SOURCE_GLB;
            if ($projectVersionId) {
                $document->project_version_id = $projectVersionId;
            }
            $document->save();

            return $this->recalculate->execute($document);
        });
    }
}
