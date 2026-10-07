<?php

namespace App\Domains\Rab\Actions;

use App\Domains\Rab\Models\RabDocument;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export dokumen RAB ke file Excel (.xlsx).
 *
 * Layout output:
 *   Baris 1-4  : Header (judul project, nama RAB, tanggal, status)
 *   Baris 5    : Header kolom tabel
 *   Baris 6-N  : Item RAB, dikelompokkan per section
 *   Baris N+1  : Subtotal, Overhead, PPN, Total
 *
 * Item bertanda "Estimasi" diberi warna berbeda.
 */
class ExportRabAction
{
    // Warna palette
    private const COLOR_HEADER_BG   = 'FF1E293B'; // slate-800
    private const COLOR_HEADER_FG   = 'FFFFFFFF'; // white
    private const COLOR_SECTION_BG  = 'FF334155'; // slate-700
    private const COLOR_SECTION_FG  = 'FFFFFFFF';
    private const COLOR_ESTIMATE_BG = 'FFF5F3FF'; // violet-50
    private const COLOR_TOTAL_BG    = 'FF0F172A'; // slate-900
    private const COLOR_TOTAL_FG    = 'FFFFFFFF';
    private const COLOR_BORDER      = 'FFE2E8F0'; // slate-200

    public function execute(RabDocument $document): StreamedResponse
    {
        $document->load(['items', 'project']);

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('RAB');

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(6);   // No
        $sheet->getColumnDimension('B')->setWidth(40);  // Uraian
        $sheet->getColumnDimension('C')->setWidth(10);  // Satuan
        $sheet->getColumnDimension('D')->setWidth(12);  // Volume
        $sheet->getColumnDimension('E')->setWidth(18);  // Harga Satuan
        $sheet->getColumnDimension('F')->setWidth(20);  // Subtotal

        $row = 1;

        // ── Header block ────────────────────────────────────────────────────
        $this->mergeAndWrite($sheet, "A{$row}:F{$row}", 'RENCANA ANGGARAN BIAYA', [
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $this->mergeAndWrite($sheet, "A{$row}:F{$row}", $document->project->title ?? '-', [
            'font'      => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $this->mergeAndWrite($sheet, "A{$row}:F{$row}", $document->title, [
            'font'      => ['size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;

        $statusLabel = $document->isFinal() ? 'FINAL' : 'DRAFT';
        $dateStr     = $document->created_at?->format('d F Y') ?? now()->format('d F Y');
        $this->mergeAndWrite($sheet, "A{$row}:F{$row}", "Status: {$statusLabel}  |  Tanggal: {$dateStr}", [
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $row++;
        $row++; // blank row

        // ── Column headers ───────────────────────────────────────────────────
        $headers = ['No.', 'Uraian Pekerjaan', 'Sat.', 'Volume', 'Harga Satuan (Rp)', 'Subtotal (Rp)'];
        foreach ($headers as $col => $title) {
            $cell = chr(65 + $col) . $row;
            $sheet->setCellValue($cell, $title);
        }
        $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_HEADER_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(20);
        $row++;

        // ── Items grouped by section ─────────────────────────────────────────
        $itemsBySection = $document->items
            ->groupBy('section')
            ->map(fn ($items) => $items->values());

        $no = 1;
        foreach ($itemsBySection as $section => $items) {
            // Section header row
            $this->mergeAndWrite($sheet, "A{$row}:F{$row}", strtoupper($section), [
                'font'      => ['bold' => true, 'color' => ['argb' => self::COLOR_SECTION_FG]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_SECTION_BG]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
            ]);
            $row++;

            foreach ($items as $item) {
                $isEstimate = (bool) $item->is_estimate;

                $sheet->setCellValue("A{$row}", $no++);
                $sheet->setCellValue("B{$row}", ($isEstimate ? '[Estimasi] ' : '') . $item->description);
                $sheet->setCellValue("C{$row}", $item->unit);
                $sheet->setCellValue("D{$row}", $item->quantity);
                $sheet->setCellValue("E{$row}", $item->unit_price);
                $sheet->setCellValue("F{$row}", $item->subtotal);

                // Number formats
                $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');

                // Estimate row — light violet background
                if ($isEstimate) {
                    $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_ESTIMATE_BG]],
                    ]);
                }

                // Borders + alignment
                $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
                ]);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$row}:F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $row++;
            }
        }

        // ── Summary rows ─────────────────────────────────────────────────────
        $summaryRows = [
            ['Subtotal',                                       $document->subtotal],
            ["Overhead ({$document->overhead_percent}%)",     $document->overhead_amount],
            ["PPN ({$document->ppn_percent}%)",               $document->ppn_amount],
            ['TOTAL',                                         $document->total],
        ];

        foreach ($summaryRows as $i => [$label, $value]) {
            $isTotal = $i === count($summaryRows) - 1;
            $bgColor = $isTotal ? self::COLOR_TOTAL_BG : 'FFEFEFEF';
            $fgColor = $isTotal ? self::COLOR_TOTAL_FG : 'FF0F172A';
            $bold    = $isTotal;

            $sheet->mergeCells("A{$row}:E{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("F{$row}", $value);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('#,##0');

            $sheet->getStyle("A{$row}:F{$row}")->applyFromArray([
                'font'      => ['bold' => $bold, 'color' => ['argb' => $fgColor]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
            ]);

            $row++;
        }

        // ── Disclaimer untuk dokumen estimasi ────────────────────────────────
        if ($document->source === RabDocument::SOURCE_GLB) {
            $row++;
            $this->mergeAndWrite($sheet, "A{$row}:F{$row}",
                '* Item berlabel [Estimasi] dihitung dari geometri model 3D. Bukan RAB final kontrak.',
                [
                    'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF7C3AED']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]
            );
        }

        // ── Stream response ──────────────────────────────────────────────────
        $filename = 'RAB_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $document->title) . '_' . now()->format('Ymd') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control'       => 'max-age=0',
                'Pragma'              => 'public',
            ],
        );
    }

    /** Merge cells, tulis nilai, dan terapkan style sekaligus. */
    private function mergeAndWrite($sheet, string $range, string $value, array $style): void
    {
        $sheet->mergeCells($range);
        $firstCell = explode(':', $range)[0];
        $sheet->setCellValue($firstCell, $value);
        if (!empty($style)) {
            $sheet->getStyle($range)->applyFromArray($style);
        }
    }
}
