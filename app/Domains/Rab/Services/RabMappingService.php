<?php

namespace App\Domains\Rab\Services;

use App\Domains\Auth\Models\User;
use App\Domains\Rab\Models\RabMapping;
use App\Domains\Rab\Models\RabPriceItem;
use Illuminate\Support\Collection;

/**
 * Mencocokkan nama/material dari row CSV/XLSX ke RabPriceItem
 * menggunakan aturan di tabel `rab_mappings`.
 *
 * match_type:
 *   - exact       : kecocokan tepat (case-insensitive)
 *   - name_pattern: wildcard sederhana, * = any chars (contoh: DINDING_BATA_*)
 *   - material    : cocokkan dari kolom material file
 *
 * Prioritas: exact > name_pattern > material
 */
class RabMappingService
{
    /** @var Collection<int, RabMapping>|null */
    private ?Collection $cachedMappings = null;

    /** @var Collection<string, RabPriceItem>|null */
    private ?Collection $cachedPriceItems = null;

    /**
     * Cari RabPriceItem yang cocok untuk sebuah nama/deskripsi.
     *
     * @param  User   $user
     * @param  string $name      Nama/deskripsi pekerjaan dari file
     * @param  string $material  Nama material (opsional, dari kolom material)
     * @return RabPriceItem|null
     */
    public function findMatch(User $user, string $name, string $material = ''): ?RabPriceItem
    {
        $mappings   = $this->getMappings($user);
        $priceItems = $this->getPriceItems($user);

        $nameLower     = strtolower(trim($name));
        $materialLower = strtolower(trim($material));

        // 1. Exact match
        foreach ($mappings->where('match_type', 'exact') as $mapping) {
            if (strtolower($mapping->pattern) === $nameLower) {
                return $priceItems->get($mapping->rab_price_item_id);
            }
        }

        // 2. Name pattern (wildcard *)
        foreach ($mappings->where('match_type', 'name_pattern') as $mapping) {
            if ($this->matchWildcard($mapping->pattern, $name)) {
                return $priceItems->get($mapping->rab_price_item_id);
            }
        }

        // 3. Material match
        if ($materialLower !== '') {
            foreach ($mappings->where('match_type', 'material') as $mapping) {
                if (strtolower($mapping->pattern) === $materialLower) {
                    return $priceItems->get($mapping->rab_price_item_id);
                }
            }
        }

        return null;
    }

    /**
     * Proses semua rows dari file dan kembalikan array hasil mapping.
     *
     * @param  User                               $user
     * @param  array<int, array<string, string>>  $rows
     * @param  string                             $nameCol      Key kolom nama/deskripsi
     * @param  string                             $qtyCol       Key kolom kuantitas
     * @param  string                             $unitCol      Key kolom satuan (opsional)
     * @param  string                             $materialCol  Key kolom material (opsional)
     * @param  string                             $sectionCol   Key kolom section/bagian (opsional)
     * @return array<int, array{
     *   row_index: int,
     *   description: string,
     *   quantity: float,
     *   unit: string,
     *   section: string,
     *   source_ref: string,
     *   price_item: RabPriceItem|null,
     *   is_mapped: bool,
     *   unit_price: float,
     * }>
     */
    public function mapRows(
        User $user,
        array $rows,
        string $nameCol,
        string $qtyCol,
        string $unitCol = '',
        string $materialCol = '',
        string $sectionCol = '',
    ): array {
        $results = [];

        foreach ($rows as $index => $row) {
            $name     = trim($row[$nameCol] ?? '');
            $qty      = $this->parseNumber($row[$qtyCol] ?? '0');
            $unit     = $unitCol !== '' ? trim($row[$unitCol] ?? '') : '';
            $material = $materialCol !== '' ? trim($row[$materialCol] ?? '') : '';
            $section  = $sectionCol !== '' ? trim($row[$sectionCol] ?? '') : 'Umum';

            if ($name === '') {
                continue; // skip baris kosong nama
            }

            $priceItem = $this->findMatch($user, $name, $material);

            $results[] = [
                'row_index'   => $index + 1,
                'description' => $name,
                'quantity'    => $qty,
                'unit'        => $unit !== '' ? $unit : ($priceItem?->unit ?? 'unit'),
                'section'     => $section !== '' ? $section : 'Umum',
                'source_ref'  => "Baris " . ($index + 1),
                'price_item'  => $priceItem,
                'is_mapped'   => $priceItem !== null,
                'unit_price'  => $priceItem?->unit_price ?? 0,
            ];
        }

        return $results;
    }

    /**
     * Simpan mapping baru dari hasil review user.
     *
     * @param  User   $user
     * @param  array  $mappings  [['name' => 'DINDING_BATA', 'price_item_id' => uuid, 'match_type' => 'exact'], ...]
     */
    public function saveMappings(User $user, array $mappings): void
    {
        foreach ($mappings as $m) {
            RabMapping::updateOrCreate(
                [
                    'user_id'    => $user->id,
                    'match_type' => $m['match_type'] ?? 'exact',
                    'pattern'    => $m['name'],
                ],
                [
                    'rab_price_item_id' => $m['price_item_id'],
                    'quantity_basis'    => $m['quantity_basis'] ?? 'area',
                ],
            );
        }

        // Invalidasi cache setelah simpan
        $this->cachedMappings = null;
    }

    // ── Internal helpers ─────────────────────────────────────────────────────

    private function getMappings(User $user): Collection
    {
        if ($this->cachedMappings === null) {
            $this->cachedMappings = RabMapping::where('user_id', $user->id)->get();
        }
        return $this->cachedMappings;
    }

    private function getPriceItems(User $user): Collection
    {
        if ($this->cachedPriceItems === null) {
            $this->cachedPriceItems = RabPriceItem::where('user_id', $user->id)
                ->get()
                ->keyBy('id');
        }
        return $this->cachedPriceItems;
    }

    /**
     * Wildcard matching: * = zero or more chars, ? = single char.
     * Case-insensitive.
     */
    private function matchWildcard(string $pattern, string $subject): bool
    {
        $regex = '/^' . str_replace(
            ['\\*', '\\?'],
            ['.*', '.'],
            preg_quote($pattern, '/'),
        ) . '$/iu';

        return (bool) preg_match($regex, $subject);
    }

    /**
     * Parse angka dari string — handle koma sebagai desimal (1.234,56 → 1234.56).
     */
    private function parseNumber(string $value): float
    {
        // Hapus separator ribuan (titik sebelum koma, atau koma sebelum titik)
        $cleaned = preg_replace('/[^\d,.\-]/', '', $value);

        // Handle format Indonesia: 1.234,56
        if (preg_match('/\d+\.\d{3},\d+/', $cleaned)) {
            $cleaned = str_replace('.', '', $cleaned);
            $cleaned = str_replace(',', '.', $cleaned);
        } elseif (str_contains($cleaned, ',') && !str_contains($cleaned, '.')) {
            // Handle 1234,56
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return (float) $cleaned;
    }
}
