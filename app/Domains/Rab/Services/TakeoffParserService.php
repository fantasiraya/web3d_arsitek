<?php

namespace App\Domains\Rab\Services;

use Illuminate\Http\UploadedFile;
use League\Csv\Reader as CsvReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Membaca file CSV atau XLSX dan mengembalikan array of rows.
 *
 * Output standar:
 *   [
 *     'headers' => ['Kolom A', 'Kolom B', ...],  // baris pertama sebagai header
 *     'rows'    => [
 *       ['Kolom A' => 'nilai', 'Kolom B' => 'nilai', ...],
 *       ...
 *     ],
 *     'raw_headers' => ['Kolom A', 'Kolom B', ...],  // alias headers
 *   ]
 */
class TakeoffParserService
{
    /** Ekstensi file yang didukung. */
    public const SUPPORTED = ['csv', 'xlsx', 'xls'];

    /**
     * Parse file ke array rows berindeks header.
     *
     * @param  UploadedFile  $file
     * @param  int           $headerRow  Nomor baris header (1-indexed, default 1)
     * @return array{headers: string[], rows: array<int, array<string, string>>, raw_headers: string[]}
     *
     * @throws RuntimeException jika format tidak didukung
     */
    public function parse(UploadedFile $file, int $headerRow = 1): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return match (true) {
            $ext === 'csv'              => $this->parseCsv($file, $headerRow),
            in_array($ext, ['xlsx', 'xls'], true) => $this->parseSpreadsheet($file, $headerRow),
            default => throw new RuntimeException("Format file '{$ext}' tidak didukung. Gunakan CSV, XLSX, atau XLS."),
        };
    }

    /**
     * Hanya baca header (baris pertama) tanpa load seluruh file.
     * Berguna untuk step "pilih kolom" di frontend sebelum import penuh.
     */
    public function peekHeaders(UploadedFile $file, int $headerRow = 1): array
    {
        $parsed = $this->parse($file, $headerRow);
        return $parsed['headers'];
    }

    // ── Internal: CSV ────────────────────────────────────────────────────────

    private function parseCsv(UploadedFile $file, int $headerRow): array
    {
        $csv = CsvReader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset($headerRow - 1); // league/csv: 0-indexed

        // Auto-detect delimiter
        $csv->setDelimiter($this->detectCsvDelimiter($file->getRealPath()));

        $headers = $csv->getHeader();
        $rows    = [];

        foreach ($csv->getRecords() as $record) {
            $rows[] = $this->normalizeRow($record, $headers);
        }

        return [
            'headers'     => $headers,
            'raw_headers' => $headers,
            'rows'        => $rows,
        ];
    }

    /**
     * Deteksi delimiter dengan membaca baris pertama dan menghitung frekuensi.
     */
    private function detectCsvDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');
        $line   = fgets($handle);
        fclose($handle);

        $delimiters = [',', ';', "\t", '|'];
        $counts     = array_map(fn ($d) => substr_count($line, $d), $delimiters);
        $max        = array_search(max($counts), $counts);

        return $delimiters[$max] ?? ',';
    }

    // ── Internal: XLSX / XLS ─────────────────────────────────────────────────

    private function parseSpreadsheet(UploadedFile $file, int $headerRow): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet       = $spreadsheet->getActiveSheet();

        $allRows = $sheet->toArray(
            nullValue: '',
            calculateFormulas: false,
            formatData: false,
            returnCellRef: false,
        );

        if (empty($allRows)) {
            return ['headers' => [], 'raw_headers' => [], 'rows' => []];
        }

        // Ambil baris header (1-indexed → 0-indexed)
        $headerIdx = $headerRow - 1;
        $headers   = array_map('strval', $allRows[$headerIdx] ?? []);

        // Trim headers dan buang kolom kosong di ujung kanan
        $headers = array_map('trim', $headers);
        while (!empty($headers) && $headers[array_key_last($headers)] === '') {
            array_pop($headers);
        }

        $rows = [];
        for ($i = $headerIdx + 1; $i < count($allRows); $i++) {
            $rawRow = $allRows[$i];

            // Skip baris kosong total
            $nonEmpty = array_filter($rawRow, fn ($v) => trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $rows[] = $this->normalizeRow(
                array_slice($rawRow, 0, count($headers)),
                $headers,
            );
        }

        return [
            'headers'     => $headers,
            'raw_headers' => $headers,
            'rows'        => $rows,
        ];
    }

    // ── Shared helpers ───────────────────────────────────────────────────────

    /**
     * Pasangkan nilai row ke header-nya, pastikan semua header terwakili.
     *
     * @param  array<mixed>   $row
     * @param  string[]       $headers
     * @return array<string, string>
     */
    private function normalizeRow(array $row, array $headers): array
    {
        $normalized = [];
        foreach ($headers as $idx => $header) {
            $key   = trim($header);
            $value = trim((string) ($row[$idx] ?? $row[$header] ?? ''));
            if ($key !== '') {
                $normalized[$key] = $value;
            }
        }
        return $normalized;
    }
}
