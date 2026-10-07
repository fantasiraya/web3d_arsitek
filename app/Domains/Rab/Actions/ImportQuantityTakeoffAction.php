<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabItem;
use App\Domains\Rab\Services\RabMappingService;
use App\Domains\Rab\Services\TakeoffParserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ImportQuantityTakeoffAction
{
    public function __construct(
        protected TakeoffParserService $parser,
        protected RabMappingService    $mappingService,
        protected RecalculateRabAction $recalculate,
    ) {}

    /**
     * STEP 1 — Parse file dan kembalikan preview (tanpa simpan ke DB).
     * Dipanggil saat user upload file di frontend untuk menampilkan
     * preview kolom + hasil mapping sebelum konfirmasi import.
     *
     * @return array{
     *   headers: string[],
     *   preview_rows: array,
     *   suggested_name_col: string,
     *   suggested_qty_col: string,
     *   suggested_unit_col: string,
     *   suggested_section_col: string,
     * }
     */
    public function preview(
        User $user,
        UploadedFile $file,
        int $headerRow = 1,
    ): array {
        $parsed  = $this->parser->parse($file, $headerRow);
        $headers = $parsed['headers'];

        // Auto-suggest kolom berdasarkan nama header yang umum
        $nameSuggestion    = $this->suggestColumn($headers, ['nama', 'uraian', 'pekerjaan', 'description', 'item', 'name']);
        $qtySuggestion     = $this->suggestColumn($headers, ['volume', 'qty', 'kuantitas', 'quantity', 'jumlah']);
        $unitSuggestion    = $this->suggestColumn($headers, ['satuan', 'unit', 'uom']);
        $sectionSuggestion = $this->suggestColumn($headers, ['bagian', 'section', 'kategori', 'group', 'kelompok']);

        // Preview hanya 10 baris pertama
        $previewRows = array_slice($parsed['rows'], 0, 10);

        return [
            'headers'               => $headers,
            'preview_rows'          => $previewRows,
            'total_rows'            => count($parsed['rows']),
            'suggested_name_col'    => $nameSuggestion,
            'suggested_qty_col'     => $qtySuggestion,
            'suggested_unit_col'    => $unitSuggestion,
            'suggested_section_col' => $sectionSuggestion,
        ];
    }

    /**
     * STEP 2 — Proses mapping: parse file + cocokkan ke price items.
     * Kembalikan hasil mapping untuk ditampilkan user (item mana yang mapped/unmapped).
     * Belum simpan ke DB.
     *
     * @return array{
     *   mapped: array,
     *   unmapped: array,
     *   total: int,
     *   mapped_count: int,
     *   unmapped_count: int,
     * }
     */
    public function dryRun(
        User $user,
        UploadedFile $file,
        string $nameCol,
        string $qtyCol,
        string $unitCol = '',
        string $materialCol = '',
        string $sectionCol = '',
        int $headerRow = 1,
    ): array {
        $parsed  = $this->parser->parse($file, $headerRow);
        $results = $this->mappingService->mapRows(
            $user,
            $parsed['rows'],
            $nameCol,
            $qtyCol,
            $unitCol,
            $materialCol,
            $sectionCol,
        );

        $mapped   = array_filter($results, fn ($r) => $r['is_mapped']);
        $unmapped = array_filter($results, fn ($r) => !$r['is_mapped']);

        return [
            'mapped'         => array_values($mapped),
            'unmapped'       => array_values($unmapped),
            'total'          => count($results),
            'mapped_count'   => count($mapped),
            'unmapped_count' => count($unmapped),
        ];
    }

    /**
     * STEP 3 — Eksekusi import: simpan items ke dokumen RAB.
     *
     * @param  array $mappedItems  Dari dryRun() yang sudah di-review user.
     *                             Boleh berisi item unmapped dengan price_item_id manual.
     * @param  array $manualPrices Key: row_index, value: ['unit_price' => float, 'price_item_id' => ?string]
     *                             Untuk item yang tidak ter-mapping otomatis tapi user isi manual.
     * @return RabDocument  Dokumen RAB yang sudah diupdate
     */
    public function execute(
        User $user,
        RabDocument $document,
        UploadedFile $file,
        string $nameCol,
        string $qtyCol,
        string $unitCol = '',
        string $materialCol = '',
        string $sectionCol = '',
        int $headerRow = 1,
        array $manualPrices = [],
    ): RabDocument {
        abort_if($document->isFinal(), 403, 'Dokumen RAB sudah final. Lakukan reopen untuk import.');

        $parsed  = $this->parser->parse($file, $headerRow);
        $results = $this->mappingService->mapRows(
            $user,
            $parsed['rows'],
            $nameCol,
            $qtyCol,
            $unitCol,
            $materialCol,
            $sectionCol,
        );

        return DB::transaction(function () use ($document, $results, $manualPrices) {
            $order = $document->items()->max('sort_order') ?? -1;

            foreach ($results as $result) {
                $rowIndex  = $result['row_index'];
                $priceItem = $result['price_item'];

                // Override manual jika ada
                $manualOverride = $manualPrices[$rowIndex] ?? null;
                $unitPrice      = $manualOverride['unit_price'] ?? $result['unit_price'];
                $priceItemId    = $manualOverride['price_item_id'] ?? $priceItem?->id;

                // Skip jika kuantitas 0 dan tidak ada mapping (baris kosong)
                if ($result['quantity'] <= 0 && $priceItemId === null && $manualOverride === null) {
                    continue;
                }

                RabItem::create([
                    'rab_document_id'   => $document->id,
                    'rab_price_item_id' => $priceItemId,
                    'section'           => $result['section'],
                    'description'       => $result['description'],
                    'unit'              => $result['unit'],
                    'quantity'          => $result['quantity'],
                    'waste_percent'     => 0,
                    'unit_price'        => $unitPrice, // snapshot harga
                    'subtotal'          => 0,
                    'source_ref'        => $result['source_ref'],
                    'is_mapped'         => $result['is_mapped'] || $manualOverride !== null,
                    'is_estimate'       => false,
                    'sort_order'        => ++$order,
                ]);
            }

            // Update source ke csv (dari manual)
            $document->source = RabDocument::SOURCE_CSV;
            $document->save();

            return $this->recalculate->execute($document);
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Cari header yang paling cocok dari daftar kandidat keyword.
     * Case-insensitive, partial match.
     */
    private function suggestColumn(array $headers, array $keywords): string
    {
        foreach ($keywords as $keyword) {
            foreach ($headers as $header) {
                if (stripos($header, $keyword) !== false) {
                    return $header;
                }
            }
        }
        return $headers[0] ?? '';
    }
}
