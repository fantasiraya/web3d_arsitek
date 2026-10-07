<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Models\RabPriceItem;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export dokumen RAB ke Excel (.xlsx) — Multi-sheet:
 *   Sheet 1  : Rekapitulasi RAB (kop surat + ringkasan per section)
 *   Sheet 2  : Daftar RAB detail (semua item)
 *   Sheet 3+ : AHSP per harga satuan yang punya komponen
 */
class ExportRabAction
{
    // ── Warna ────────────────────────────────────────────────────────────────
    private const HDR_BG    = 'FF1E293B'; // slate-800
    private const HDR_FG    = 'FFFFFFFF';
    private const SEC_BG    = 'FF334155'; // slate-700
    private const SEC_FG    = 'FFFFFFFF';
    private const EST_BG    = 'FFF5F3FF'; // violet-50
    private const TOT_BG    = 'FF0F172A'; // slate-900
    private const TOT_FG    = 'FFFFFFFF';
    private const BORD      = 'FFE2E8F0';
    private const YELLOW_BG = 'FFFFFF99'; // kuning AHSP
    private const AHSP_HEAD = 'FF1E3A5F'; // biru tua header AHSP
    private const TEN_BG    = 'FFDBEAFE'; // biru muda (tenaga)
    private const MAT_BG    = 'FFD1FAE5'; // hijau muda (bahan)
    private const EQP_BG    = 'FFFEF3C7'; // kuning muda (peralatan)

    public function execute(RabDocument $document): StreamedResponse
    {
        $document->load(['items.priceItem.components', 'project', 'owner']);

        // Ambil data profil arsitek pemilik dokumen
        $owner = $document->owner;

        $spreadsheet = new Spreadsheet();

        // ── Sheet 1: Rekapitulasi ────────────────────────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Rekapitulasi RAB');
        $this->buildRekapSheet($sheet1, $document, $owner);

        // ── Sheet 2: Daftar RAB Detail ───────────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Daftar RAB');
        $this->buildRabDetailSheet($sheet2, $document, $owner);

        // ── Sheet 3+: AHSP per price item ────────────────────────────────────
        $ahspItems = collect($document->items)
            ->filter(fn ($item) => $item->priceItem?->has_components)
            ->map(fn ($item) => $item->priceItem)
            ->unique('id')
            ->values();

        foreach ($ahspItems as $idx => $priceItem) {
            $priceItem->load('components');
            $title = 'AHSP ' . ($idx + 1);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($title);
            $this->buildAhspSheet($sheet, $priceItem, $idx + 1, $owner);
        }

        $filename = 'RAB_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $document->title)
                  . '_' . now()->format('Ymd') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            fn () => $writer->save('php://output'),
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'max-age=0',
                'Pragma'              => 'public',
            ],
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // Sheet 1 — Rekapitulasi (ringkasan per section)
    // ════════════════════════════════════════════════════════════════════════
    private function buildRekapSheet($sheet, RabDocument $document, ?User $owner = null): void
    {
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(45);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(22);

        $row = 1;

        // Kop surat arsitek
        $row = $this->buildLetterhead($sheet, $owner, 'A', 'D', $row);

        // Header dokumen RAB
        $this->merge($sheet, "A{$row}:D{$row}", 'REKAPITULASI RENCANA ANGGARAN BIAYA', bold: true, size: 14, center: true);
        $row++;
        $this->merge($sheet, "A{$row}:D{$row}", $document->project->title ?? '-', bold: true, size: 12, center: true);
        $row++;
        $this->merge($sheet, "A{$row}:D{$row}", $document->title, size: 11, center: true);
        $row++;
        $statusLabel = $document->isFinal() ? 'FINAL' : 'DRAFT';
        $this->merge($sheet, "A{$row}:D{$row}",
            "Status: {$statusLabel}  |  Tanggal: " . ($document->created_at?->format('d F Y') ?? now()->format('d F Y')),
            italic: true, size: 9, color: '64748B', center: true);
        $row += 2;

        // Header kolom
        foreach (['No.', 'Uraian Pekerjaan', 'Jumlah Harga (Rp)', 'Keterangan'] as $col => $h) {
            $sheet->setCellValue(chr(65 + $col) . $row, $h);
        }
        $this->styleRange($sheet, "A{$row}:D{$row}", bg: self::HDR_BG, fg: self::HDR_FG, bold: true, center: true);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        // Kelompokkan per section, tampilkan subtotal
        $sections = $document->items->groupBy('section');
        $no       = 1;

        foreach ($sections as $section => $items) {
            // Section header
            $sheet->setCellValue("A{$row}", '');
            $sheet->setCellValue("B{$row}", strtoupper($section));
            $this->styleRange($sheet, "A{$row}:D{$row}", bg: self::SEC_BG, fg: self::SEC_FG, bold: true);
            $sheet->mergeCells("A{$row}:D{$row}");
            $row++;

            $sectionTotal = 0;
            foreach ($items as $item) {
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValue("B{$row}", $item->description);
                $sheet->setCellValue("C{$row}", $item->subtotal);
                $sheet->setCellValue("D{$row}", $item->is_estimate ? 'Estimasi' : '');
                $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $this->border($sheet, "A{$row}:D{$row}");
                if ($item->is_estimate) {
                    $this->styleRange($sheet, "A{$row}:D{$row}", bg: self::EST_BG);
                }
                $sectionTotal += $item->subtotal;
                $row++;
            }

            // Subtotal section
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", "  Jumlah {$section}");
            $sheet->setCellValue("C{$row}", $sectionTotal);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $this->styleRange($sheet, "A{$row}:D{$row}", bg: 'FFEFF6FF', bold: true);
            $this->border($sheet, "A{$row}:D{$row}");
            $row++;
        }

        $row++;
        $this->summaryRows($sheet, $row, $document, 'A', 'B', 'C', 3);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Sheet 2 — Daftar RAB Detail (semua item)
    // ════════════════════════════════════════════════════════════════════════
    private function buildRabDetailSheet($sheet, RabDocument $document, ?User $owner = null): void
    {
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(42);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(12);

        $row = 1;

        // Kop surat
        $row = $this->buildLetterhead($sheet, $owner, 'A', 'G', $row);

        $this->merge($sheet, "A{$row}:G{$row}", 'RENCANA ANGGARAN BIAYA — DETAIL', bold: true, size: 13, center: true);
        $row++;
        $this->merge($sheet, "A{$row}:G{$row}", $document->title . ' | ' . ($document->project->title ?? '-'), size: 11, center: true);
        $row += 2;

        // Header kolom
        foreach (['No.', 'Uraian Pekerjaan', 'Sat.', 'Volume', 'Harga Satuan (Rp)', 'Subtotal (Rp)', 'Ref. AHSP'] as $col => $h) {
            $sheet->setCellValue(chr(65 + $col) . $row, $h);
        }
        $this->styleRange($sheet, "A{$row}:G{$row}", bg: self::HDR_BG, fg: self::HDR_FG, bold: true, center: true);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        $sections = $document->items->groupBy('section');
        $no       = 1;

        // Build lookup AHSP sheet index
        $ahspItems   = collect($document->items)
            ->filter(fn ($i) => $i->priceItem?->has_components)
            ->map(fn ($i) => $i->priceItem)
            ->unique('id')
            ->values();
        $ahspIndex   = $ahspItems->mapWithKeys(fn ($p, $i) => [$p->id => 'AHSP ' . ($i + 1)]);

        foreach ($sections as $section => $items) {
            $sheet->setCellValue("A{$row}", '');
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", strtoupper($section));
            $this->styleRange($sheet, "A{$row}:G{$row}", bg: self::SEC_BG, fg: self::SEC_FG, bold: true);
            $row++;

            foreach ($items as $item) {
                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValue("B{$row}", ($item->is_estimate ? '[Est] ' : '') . $item->description);
                $sheet->setCellValue("C{$row}", $item->unit);
                $sheet->setCellValue("D{$row}", $item->quantity);
                $sheet->setCellValue("E{$row}", $item->unit_price);
                $sheet->setCellValue("F{$row}", $item->subtotal);
                $sheet->setCellValue("G{$row}", $item->priceItem?->has_components
                    ? ($ahspIndex[$item->priceItem->id] ?? '')
                    : '');

                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $this->border($sheet, "A{$row}:G{$row}");
                if ($item->is_estimate) {
                    $this->styleRange($sheet, "A{$row}:G{$row}", bg: self::EST_BG);
                }
                $row++;
            }
        }

        $row++;
        $this->summaryRows($sheet, $row, $document, 'A', 'E', 'F', 6);
    }

    // ════════════════════════════════════════════════════════════════════════
    // Sheet 3+ — AHSP per harga satuan
    // Format persis seperti gambar referensi
    // ════════════════════════════════════════════════════════════════════════
    private function buildAhspSheet($sheet, RabPriceItem $priceItem, int $num, ?User $owner = null): void
    {
        $sheet->getColumnDimension('A')->setWidth(6);   // No
        $sheet->getColumnDimension('B')->setWidth(38);  // Item
        $sheet->getColumnDimension('C')->setWidth(12);  // Kode
        $sheet->getColumnDimension('D')->setWidth(10);  // Satuan
        $sheet->getColumnDimension('E')->setWidth(12);  // Koefisien
        $sheet->getColumnDimension('F')->setWidth(20);  // Harga Satuan
        $sheet->getColumnDimension('G')->setWidth(22);  // Jumlah Harga

        $row = 1;

        // Kop surat — center, A:G
        $row = $this->buildLetterhead($sheet, $owner, 'A', 'G', $row);

        // ── Header analisa ──────────────────────────────────────────────────
        $sheet->mergeCells("A{$row}:G{$row}");
        $sheet->setCellValue("A{$row}", "{$num}");
        $sheet->getStyle("A{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF' . self::AHSP_HEAD]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $row++;

        $sheet->mergeCells("A{$row}:G{$row}");
        $codeStr = $priceItem->code ? "[{$priceItem->code}]  " : '';
        $sheet->setCellValue("A{$row}", $codeStr . strtoupper($priceItem->name) . "   (1 {$priceItem->unit})");
        $this->styleRange($sheet, "A{$row}:G{$row}", bg: self::AHSP_HEAD, fg: 'FFFFFFFF', bold: true, size: 11);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row += 2;

        // ── Header kolom ───────────────────────────────────────────────────
        foreach (['No', 'Item', 'Kode', 'Satuan', 'Koefisien', 'Harga Satuan', 'Jumlah Harga'] as $col => $h) {
            $sheet->setCellValue(chr(65 + $col) . $row, $h);
        }
        $this->styleRange($sheet, "A{$row}:G{$row}", bg: '334155', fg: 'FFFFFF', bold: true, center: true);
        $sheet->getRowDimension($row)->setRowHeight(18);
        $row++;

        // ── Kelompok komponen ──────────────────────────────────────────────
        $typeLabels = ['tenaga' => 'Tenaga', 'bahan' => 'Bahan', 'peralatan' => 'Peralatan'];
        $typeBg     = ['tenaga' => self::TEN_BG, 'bahan' => self::MAT_BG, 'peralatan' => self::EQP_BG];

        $base = 0.0;

        foreach (['tenaga', 'bahan', 'peralatan'] as $type) {
            $comps = $priceItem->components->where('component_type', $type)->values();

            // Group header
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", '    ' . $typeLabels[$type]);
            $this->styleRange($sheet, "A{$row}:G{$row}", bg: $typeBg[$type], bold: true);
            $this->border($sheet, "A{$row}:G{$row}");
            $row++;

            $subtotal = 0.0;
            $no       = 1;

            if ($comps->isEmpty()) {
                $sheet->mergeCells("A{$row}:G{$row}");
                $sheet->setCellValue("A{$row}", '    — (tidak ada) —');
                $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF94A3B8');
                $this->border($sheet, "A{$row}:G{$row}");
                $row++;
            } else {
                foreach ($comps as $comp) {
                    $sheet->setCellValue("A{$row}", $no++);
                    $sheet->setCellValue("B{$row}", $comp->name);
                    $sheet->setCellValue("C{$row}", $comp->code ?? '');
                    $sheet->setCellValue("D{$row}", $comp->unit);
                    $sheet->setCellValue("E{$row}", $comp->coefficient);
                    $sheet->setCellValue("F{$row}", $comp->unit_price);
                    $sheet->setCellValue("G{$row}", $comp->amount);

                    $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.0000');
                    $sheet->getStyle("F{$row}:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("E{$row}:G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $this->border($sheet, "A{$row}:G{$row}");
                    $subtotal += $comp->amount;
                    $row++;
                }
            }

            // Subtotal kelompok
            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", 'Jumlah');
            $sheet->setCellValue("F{$row}", '');
            $sheet->setCellValue("G{$row}", $subtotal);
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $this->styleRange($sheet, "A{$row}:G{$row}", bg: $typeBg[$type], bold: true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $this->border($sheet, "A{$row}:G{$row}");

            $base += $subtotal;
            $row++;
        }

        // ── Jumlah, Overhead & Profit, Harga Satuan ────────────────────────
        $overhead  = round($base * ($priceItem->overhead_percent / 100), 2);
        $unitPrice = round($base + $overhead, 2);
        $row++;

        // Jumlah base
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Jumlah');
        $sheet->setCellValue("G{$row}", $base);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->styleRange($sheet, "A{$row}:G{$row}", bold: true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->border($sheet, "A{$row}:G{$row}");
        $row++;

        // Overhead & Profit
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", 'Overhead & Profit');
        $sheet->setCellValue("F{$row}", number_format($priceItem->overhead_percent, 0) . '%');
        $sheet->setCellValue("G{$row}", $overhead);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->border($sheet, "A{$row}:G{$row}");
        $row++;

        // Harga Satuan Pekerjaan — kuning
        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'Harga Satuan Pekerjaan');
        $sheet->setCellValue("G{$row}", $unitPrice);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        $this->styleRange($sheet, "A{$row}:G{$row}", bg: self::YELLOW_BG, bold: true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $this->border($sheet, "A{$row}:G{$row}");
    }

    // ════════════════════════════════════════════════════════════════════════
    // Kop Surat Arsitek — tanpa nama personal, semua center
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Tulis kop surat di atas sheet. Semua baris di-center.
     * Hanya menampilkan nama badan usaha, alamat, dan telepon (tanpa nama personal).
     * Return baris berikutnya setelah kop.
     */
    private function buildLetterhead($sheet, ?User $owner, string $colStart, string $colEnd, int $startRow): int
    {
        if (!$owner) return $startRow;

        $companyType = $owner->company_type ?? '';
        $companyName = $owner->company_name ?? '';
        $phone       = $owner->phone ?? '';

        // Nama header: "PT Nama Perusahaan" atau nama arsitek jika tidak ada badan usaha
        if ($companyName) {
            $headerName = ($companyType && $companyType !== 'Perorangan')
                ? strtoupper("{$companyType} {$companyName}")
                : strtoupper($companyName);
        } else {
            return $startRow; // tidak ada nama badan usaha → skip kop
        }

        // Susun baris alamat
        $addressParts = array_filter([
            $owner->address       ?? '',
            $owner->village_name  ?? '',
            $owner->district_name ?? '',
        ]);
        $addressLine1 = implode(', ', $addressParts);

        $cityParts = array_filter([
            $owner->city_name     ?? '',
            $owner->province_name ?? '',
            $owner->postal_code   ?? '',
        ]);
        $addressLine2 = implode(', ', $cityParts);

        $row = $startRow;

        // Garis atas kop (border top medium)
        $lastCol = $colEnd;
        $sheet->getStyle("{$colStart}{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']]],
        ]);

        // Nama badan usaha — bold besar, center
        $this->merge($sheet, "{$colStart}{$row}:{$lastCol}{$row}", $headerName,
            bold: true, size: 14, center: true);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        // Alamat baris 1 — center
        if ($addressLine1) {
            $this->merge($sheet, "{$colStart}{$row}:{$lastCol}{$row}", $addressLine1,
                size: 9, center: true, color: '475569');
            $row++;
        }

        // Kota, provinsi — center; telepon di baris yang sama
        $contactLine = trim(implode('   |   ', array_filter([
            $addressLine2,
            $phone ? "Telp: {$phone}" : '',
        ])));
        if ($contactLine) {
            $this->merge($sheet, "{$colStart}{$row}:{$lastCol}{$row}", $contactLine,
                size: 9, center: true, color: '475569');
            $row++;
        }

        // Garis bawah kop (border bottom medium)
        $sheet->getStyle("{$colStart}{$row}:{$lastCol}{$row}")->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E293B']]],
        ]);
        $row++;
        $row++; // blank row setelah kop

        return $row;
    }

    // ════════════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════════════

    /** Baris summary: Subtotal / Overhead / PPN / Total. */
    private function summaryRows($sheet, int &$row, RabDocument $document, string $labelStart, string $labelEnd, string $valueCol, int $totalCols): void
    {
        $summaries = [
            ['Subtotal',                                    $document->subtotal,       false],
            ["Overhead ({$document->overhead_percent}%)",  $document->overhead_amount, false],
            ["PPN ({$document->ppn_percent}%)",            $document->ppn_amount,      false],
            ['TOTAL',                                      $document->total,           true],
        ];

        foreach ($summaries as [$label, $value, $isTotal]) {
            $bg   = $isTotal ? self::TOT_BG : 'FFEFEFEF';
            $fg   = $isTotal ? self::TOT_FG : 'FF0F172A';
            $bold = $isTotal;

            $sheet->mergeCells("{$labelStart}{$row}:{$labelEnd}{$row}");
            $sheet->setCellValue("{$labelStart}{$row}", $label);
            $sheet->setCellValue("{$valueCol}{$row}", $value);
            $sheet->getStyle("{$valueCol}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $this->styleRange($sheet, "{$labelStart}{$row}:{$valueCol}{$row}", bg: $bg, fg: $fg, bold: $bold);
            $sheet->getStyle("{$labelStart}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$valueCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $this->border($sheet, "{$labelStart}{$row}:{$valueCol}{$row}");
            $row++;
        }
    }

    private function merge($sheet, string $range, string $value,
        bool $bold = false, int $size = 10, bool $center = false,
        bool $italic = false, string $color = '0F172A'): void
    {
        $sheet->mergeCells($range);
        $first = explode(':', $range)[0];
        $sheet->setCellValue($first, $value);
        $style = ['font' => ['bold' => $bold, 'size' => $size, 'italic' => $italic, 'color' => ['argb' => 'FF' . $color]]];
        if ($center) $style['alignment'] = ['horizontal' => Alignment::HORIZONTAL_CENTER];
        $sheet->getStyle($range)->applyFromArray($style);
    }

    private function styleRange($sheet, string $range, string $bg = '', string $fg = '0F172A',
        bool $bold = false, int $size = 10, bool $center = false): void
    {
        $style = ['font' => ['bold' => $bold, 'size' => $size, 'color' => ['argb' => 'FF' . $fg]]];
        if ($bg) $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . ltrim($bg, 'FF')]];
        if ($center) $style['alignment'] = ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER];
        $sheet->getStyle($range)->applyFromArray($style);
    }

    private function border($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::BORD]]],
        ]);
    }
}
