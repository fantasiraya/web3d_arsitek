<?php

namespace App\Domains\Drawing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Services\DrawingAccessService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSvgAction
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    /**
     * Export DrawingSheet elements to SVG format.
     */
    public function execute(User $user, DrawingSheet $sheet, ?string $rawSvg = null, ?string $frameId = null): StreamedResponse
    {
        $this->accessService->authorizeView($user, $sheet->project, $sheet);

        $targetTitle = $sheet->title;
        if ($frameId && $frameId !== 'main_frame') {
            $frameEl = $sheet->elements()->where('id', $frameId)->first();
            if ($frameEl && $frameEl->type === 'paper_frame') {
                $fData = is_array($frameEl->data) ? $frameEl->data : json_decode($frameEl->data ?? '[]', true);
                if (!empty($fData['title'])) {
                    $targetTitle = $fData['title'];
                }
            }
        }

        $svgContent = $rawSvg ?: $this->buildSvgFromElements($sheet, $frameId);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $targetTitle) . '.svg';

        return response()->streamDownload(function () use ($svgContent) {
            echo $svgContent;
        }, $filename, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function buildSvgFromElements(DrawingSheet $sheet, ?string $frameId = null): string
    {
        $width = $sheet->width ?: ($sheet->orientation === 'portrait' ? 891 : 1260);
        $height = $sheet->height ?: ($sheet->orientation === 'portrait' ? 1260 : 891);
        $sheetTitle = $sheet->title;

        $paperX = 60;
        $paperY = 60;

        if ($frameId && $frameId !== 'main_frame') {
            $frameEl = $sheet->elements()->where('id', $frameId)->first();
            if ($frameEl && $frameEl->type === 'paper_frame') {
                $fData = is_array($frameEl->data) ? $frameEl->data : json_decode($frameEl->data ?? '[]', true);
                $paperX = (float) ($fData['x'] ?? 60);
                $paperY = (float) ($fData['y'] ?? 60);
                $width = (float) ($fData['width'] ?? $width);
                $height = (float) ($fData['height'] ?? $height);
                if (!empty($fData['title'])) {
                    $sheetTitle = $fData['title'];
                }
            }
        }

        $svg = [];
        $svg[] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $svg[] = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$width} {$height}\" width=\"{$width}\" height=\"{$height}\">";
        $svg[] = "  <defs>";
        $svg[] = "    <style>";
        $svg[] = "      text { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }";
        $svg[] = "    </style>";
        $svg[] = "  </defs>";

        // 1. Kertas Putih Dasar
        $svg[] = "  <!-- Kertas Lembar Kerja CAD -->";
        $svg[] = "  <rect width=\"100%\" height=\"100%\" fill=\"#ffffff\"/>";

        // 2. Garis Tepi Arsitektur Standar ISO (Margin 20px)
        $margin = 20;
        $frameW = $width - $margin * 2;
        $frameH = $height - $margin * 2;
        $svg[] = "  <!-- Garis Batas ISO Margin -->";
        $svg[] = "  <rect x=\"{$margin}\" y=\"{$margin}\" width=\"{$frameW}\" height=\"{$frameH}\" stroke=\"#1e293b\" stroke-width=\"2\" fill=\"none\"/>";

        // 3. Kop Arsitektur (Title Block) di Pojok Kanan Bawah
        $kopW = min(320, $width - 60);
        $kopH = 80;
        $kopX = $width - $kopW - $margin;
        $kopY = $height - $kopH - $margin;

        $svg[] = "  <!-- Kop Judul Gambar / Title Block -->";
        $svg[] = "  <g id=\"title-block\">";
        $svg[] = "    <rect x=\"{$kopX}\" y=\"{$kopY}\" width=\"{$kopW}\" height=\"{$kopH}\" stroke=\"#1e293b\" stroke-width=\"1.5\" fill=\"#f8fafc\"/>";

        $projectTitle = htmlspecialchars($sheet->project->title ?? 'Proyek Arsitektur', ENT_XML1);
        $sheetTitle = htmlspecialchars($sheet->title, ENT_XML1);
        $scale = htmlspecialchars($sheet->scale, ENT_XML1);
        $paperSize = htmlspecialchars($sheet->paper_size ?? 'A3', ENT_XML1);
        $dateStr = date('d/m/Y');

        $svg[] = "    <text x=\"" . ($kopX + 10) . "\" y=\"" . ($kopY + 18) . "\" font-size=\"10\" font-weight=\"bold\" fill=\"#4338ca\">STUDIO ARSITEKTUR &amp; DESAIN</text>";
        $svg[] = "    <text x=\"" . ($kopX + 10) . "\" y=\"" . ($kopY + 36) . "\" font-size=\"13\" font-weight=\"bold\" fill=\"#0f172a\">{$sheetTitle}</text>";
        $svg[] = "    <text x=\"" . ($kopX + 10) . "\" y=\"" . ($kopY + 54) . "\" font-size=\"10\" fill=\"#475569\">Proyek: {$projectTitle}</text>";
        $svg[] = "    <text x=\"" . ($kopX + 10) . "\" y=\"" . ($kopY + 70) . "\" font-size=\"9\" font-family=\"monospace\" fill=\"#64748b\">Skala: {$scale}  •  Kertas: {$paperSize}  •  {$dateStr}</text>";
        $svg[] = "  </g>";

        // 4. Gambar Seluruh Elemen CAD Arsitektur
        $elements = $sheet->elements()->orderBy('sort_order')->get();

        $svg[] = "  <!-- Elemen Gambar Kerja CAD -->";
        $svg[] = "  <g id=\"cad-elements\">";

        foreach ($elements as $el) {
            if ($el->type === 'paper_frame') {
                continue;
            }

            $data = is_array($el->data) ? $el->data : json_decode($el->data ?? '[]', true);
            $type = $el->type;

            switch ($type) {
                case 'wall': {
                    $x1 = ($data['x1'] ?? $data['x'] ?? 0) - $paperX;
                    $y1 = ($data['y1'] ?? $data['y'] ?? 0) - $paperY;
                    $x2 = ($data['x2'] ?? ($data['x'] ?? 0) + ($data['width'] ?? 0)) - $paperX;
                    $y2 = ($data['y2'] ?? ($data['y'] ?? 0) + ($data['height'] ?? 0)) - $paperY;
                    $thickness = (float) ($data['thickness'] ?? 12);

                    // Garis dinding ganda tebal arsitektur + garis as sumbu tengah
                    $svg[] = "    <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"#1e293b\" stroke-width=\"{$thickness}\" stroke-linecap=\"square\"/>";
                    $svg[] = "    <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"#94a3b8\" stroke-width=\"1.5\" stroke-dasharray=\"4,4\"/>";
                    break;
                }

                case 'line': {
                    $x1 = ($data['x1'] ?? $data['x'] ?? 0) - $paperX;
                    $y1 = ($data['y1'] ?? $data['y'] ?? 0) - $paperY;
                    $x2 = ($data['x2'] ?? ($data['x'] ?? 0) + ($data['width'] ?? 0)) - $paperX;
                    $y2 = ($data['y2'] ?? ($data['y'] ?? 0) + ($data['height'] ?? 0)) - $paperY;
                    $stroke = $data['stroke'] ?? '#64748b';
                    $strokeWidth = $data['strokeWidth'] ?? 2;
                    $svg[] = "    <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"{$stroke}\" stroke-width=\"{$strokeWidth}\" stroke-linecap=\"round\"/>";
                    break;
                }

                case 'column': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $size = $data['size'] ?? 30;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$size}\" height=\"{$size}\" stroke=\"#0f172a\" stroke-width=\"2\" fill=\"#334155\"/>";
                    $svg[] = "      <line x1=\"{$x}\" y1=\"{$y}\" x2=\"" . ($x + $size) . "\" y2=\"" . ($y + $size) . "\" stroke=\"#94a3b8\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <line x1=\"{$x}\" y1=\"" . ($y + $size) . "\" x2=\"" . ($x + $size) . "\" y2=\"{$y}\" stroke=\"#94a3b8\" stroke-width=\"1.5\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'door': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $size = $data['size'] ?? 80;

                    $svg[] = "    <g>";
                    // Daun / kusen pintu
                    $svg[] = "      <line x1=\"{$x}\" y1=\"{$y}\" x2=\"{$x}\" y2=\"" . ($y + $size) . "\" stroke=\"#16a34a\" stroke-width=\"3\" stroke-linecap=\"round\"/>";
                    // Busur swing pintu
                    $svg[] = "      <path d=\"M {$x} " . ($y + $size) . " A {$size} {$size} 0 0 1 " . ($x + $size) . " {$y}\" stroke=\"#16a34a\" stroke-width=\"1.5\" stroke-dasharray=\"4,3\" fill=\"none\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'window': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 100;
                    $h = $data['height'] ?? 16;
                    $midH = $y + ($h / 2);

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" stroke=\"#0284c7\" stroke-width=\"2\" fill=\"#f0f9ff\"/>";
                    $svg[] = "      <line x1=\"{$x}\" y1=\"{$midH}\" x2=\"" . ($x + $w) . "\" y2=\"{$midH}\" stroke=\"#0284c7\" stroke-width=\"2\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'stair': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 80;
                    $h = $data['height'] ?? 160;
                    $steps = 8;
                    $stepH = $h / $steps;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" stroke=\"#475569\" stroke-width=\"2\" fill=\"#f8fafc\"/>";
                    for ($s = 1; $s < $steps; $s++) {
                        $sy = $y + ($s * $stepH);
                        $svg[] = "      <line x1=\"{$x}\" y1=\"{$sy}\" x2=\"" . ($x + $w) . "\" y2=\"{$sy}\" stroke=\"#64748b\" stroke-width=\"1\"/>";
                    }
                    $arrowX = $x + ($w / 2);
                    $svg[] = "      <line x1=\"{$arrowX}\" y1=\"" . ($y + $h - 15) . "\" x2=\"{$arrowX}\" y2=\"" . ($y + 20) . "\" stroke=\"#2563eb\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <polygon points=\"" . ($arrowX - 4) . "," . ($y + 24) . " " . ($arrowX + 4) . "," . ($y + 24) . " {$arrowX}," . ($y + 14) . "\" fill=\"#2563eb\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'toilet': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 50;
                    $h = $data['height'] ?? 45;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"14\" rx=\"3\" stroke=\"#0284c7\" stroke-width=\"1.5\" fill=\"#e0f2fe\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 4) . "\" y=\"" . ($y + 14) . "\" width=\"" . ($w - 8) . "\" height=\"" . ($h - 14) . "\" rx=\"12\" stroke=\"#0284c7\" stroke-width=\"1.5\" fill=\"#ffffff\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'bed': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 120;
                    $h = $data['height'] ?? 140;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"6\" stroke=\"#4338ca\" stroke-width=\"2\" fill=\"#e0e7ff\"/>";
                    $pillowW = ($w / 2) - 14;
                    $svg[] = "      <rect x=\"" . ($x + 8) . "\" y=\"" . ($y + 8) . "\" width=\"{$pillowW}\" height=\"22\" rx=\"4\" stroke=\"#6366f1\" stroke-width=\"1.5\" fill=\"#ffffff\"/>";
                    $svg[] = "      <rect x=\"" . ($x + $w / 2 + 6) . "\" y=\"" . ($y + 8) . "\" width=\"{$pillowW}\" height=\"22\" rx=\"4\" stroke=\"#6366f1\" stroke-width=\"1.5\" fill=\"#ffffff\"/>";
                    $svg[] = "      <line x1=\"{$x}\" y1=\"" . ($y + 45) . "\" x2=\"" . ($x + $w) . "\" y2=\"" . ($y + 45) . "\" stroke=\"#818cf8\" stroke-width=\"1.5\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'table': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 100;
                    $h = $data['height'] ?? 70;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"" . ($x + 8) . "\" y=\"" . ($y + 8) . "\" width=\"" . ($w - 16) . "\" height=\"" . ($h - 16) . "\" rx=\"4\" stroke=\"#d97706\" stroke-width=\"2\" fill=\"#fef3c7\"/>";
                    $chairW = $w - 32;
                    $svg[] = "      <rect x=\"" . ($x + 16) . "\" y=\"{$y}\" width=\"{$chairW}\" height=\"6\" rx=\"2\" stroke=\"#d97706\" stroke-width=\"1.5\" fill=\"#fef3c7\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 16) . "\" y=\"" . ($y + $h - 6) . "\" width=\"{$chairW}\" height=\"6\" rx=\"2\" stroke=\"#d97706\" stroke-width=\"1.5\" fill=\"#fef3c7\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'rect': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 100;
                    $h = $data['height'] ?? 100;
                    $stroke = $data['stroke'] ?? '#1e293b';
                    $fill = $data['fill'] ?? 'none';
                    $svg[] = "    <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" stroke=\"{$stroke}\" stroke-width=\"2\" fill=\"{$fill}\"/>";
                    break;
                }

                case 'dimension': {
                    $x1 = ($data['x1'] ?? 0) - $paperX;
                    $y1 = ($data['y1'] ?? 0) - $paperY;
                    $x2 = ($data['x2'] ?? 0) - $paperX;
                    $y2 = ($data['y2'] ?? 0) - $paperY;

                    $dx = $x2 - $x1;
                    $dy = $y2 - $y1;
                    $lengthMm = round(sqrt($dx * $dx + $dy * $dy) * 10);
                    $midX = ($x1 + $x2) / 2;
                    $midY = ($y1 + $y2) / 2;

                    $svg[] = "    <g>";
                    $svg[] = "      <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"#f59e0b\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <line x1=\"" . ($x1 - 4) . "\" y1=\"" . ($y1 + 4) . "\" x2=\"" . ($x1 + 4) . "\" y2=\"" . ($y1 - 4) . "\" stroke=\"#f59e0b\" stroke-width=\"2\"/>";
                    $svg[] = "      <line x1=\"" . ($x2 - 4) . "\" y1=\"" . ($y2 + 4) . "\" x2=\"" . ($x2 + 4) . "\" y2=\"" . ($y2 - 4) . "\" stroke=\"#f59e0b\" stroke-width=\"2\"/>";
                    $svg[] = "      <text x=\"{$midX}\" y=\"" . ($midY - 4) . "\" text-anchor=\"middle\" font-family=\"monospace\" font-size=\"11\" font-weight=\"bold\" fill=\"#d97706\">{$lengthMm} mm</text>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'room_label': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $rawText = $data['text'] ?? "R. Tamu\n±0.00";
                    $lines = explode("\n", $rawText);

                    $svg[] = "    <g>";
                    foreach ($lines as $i => $lineText) {
                        $lineEsc = htmlspecialchars($lineText, ENT_XML1);
                        $curY = $y + ($i * 14);
                        $weight = $i === 0 ? "bold" : "normal";
                        $fill = $i === 0 ? "#0f172a" : "#64748b";
                        $svg[] = "      <text x=\"{$x}\" y=\"{$curY}\" font-size=\"11\" font-weight=\"{$weight}\" fill=\"{$fill}\" text-anchor=\"middle\">{$lineEsc}</text>";
                    }
                    $svg[] = "    </g>";
                    break;
                }

                case 'text': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $text = htmlspecialchars($data['text'] ?? 'Label', ENT_XML1);
                    $fontSize = $data['fontSize'] ?? 14;
                    $fill = $data['fill'] ?? '#0f172a';
                    $svg[] = "    <text x=\"{$x}\" y=\"{$y}\" font-size=\"{$fontSize}\" fill=\"{$fill}\">{$text}</text>";
                    break;
                }
            }
        }

        $svg[] = "  </g>";
        $svg[] = "</svg>";

        return implode("\n", $svg);
    }
}
