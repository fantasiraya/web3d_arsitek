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

                case 'double_door': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 140;
                    $leaf = $w / 2;

                    $svg[] = "    <g>";
                    $svg[] = "      <line x1=\"{$x}\" y1=\"{$y}\" x2=\"{$x}\" y2=\"" . ($y + $leaf) . "\" stroke=\"#16a34a\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>";
                    $svg[] = "      <line x1=\"" . ($x + $w) . "\" y1=\"{$y}\" x2=\"" . ($x + $w) . "\" y2=\"" . ($y + $leaf) . "\" stroke=\"#16a34a\" stroke-width=\"2.5\" stroke-linecap=\"round\"/>";
                    $svg[] = "      <path d=\"M {$x} " . ($y + $leaf) . " A {$leaf} {$leaf} 0 0 1 " . ($x + $leaf) . " {$y}\" stroke=\"#16a34a\" stroke-width=\"1.5\" stroke-dasharray=\"4,3\" fill=\"none\"/>";
                    $svg[] = "      <path d=\"M " . ($x + $w) . " " . ($y + $leaf) . " A {$leaf} {$leaf} 0 0 0 " . ($x + $leaf) . " {$y}\" stroke=\"#16a34a\" stroke-width=\"1.5\" stroke-dasharray=\"4,3\" fill=\"none\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'sliding_door': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 120;
                    $h = $data['height'] ?? 16;
                    $pW = $w * 0.52;
                    $pH = $h / 2 - 2;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" stroke=\"#0284c7\" stroke-width=\"2\" fill=\"#f0f9ff\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 2) . "\" y=\"" . ($y + 2) . "\" width=\"{$pW}\" height=\"{$pH}\" stroke=\"#0369a1\" stroke-width=\"1.5\" fill=\"#bae6fd\"/>";
                    $svg[] = "      <rect x=\"" . ($x + $w * 0.46) . "\" y=\"" . ($y + $h / 2) . "\" width=\"{$pW}\" height=\"{$pH}\" stroke=\"#0369a1\" stroke-width=\"1.5\" fill=\"#bae6fd\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'sofa': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 140;
                    $h = $data['height'] ?? 65;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"6\" stroke=\"#78350f\" stroke-width=\"2\" fill=\"#fef3c7\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 6) . "\" y=\"" . ($y + 4) . "\" width=\"" . ($w - 12) . "\" height=\"14\" rx=\"4\" stroke=\"#92400e\" stroke-width=\"1.5\" fill=\"#fde68a\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 4) . "\" y=\"" . ($y + 4) . "\" width=\"14\" height=\"" . ($h - 8) . "\" rx=\"4\" stroke=\"#92400e\" stroke-width=\"1.5\" fill=\"#fde68a\"/>";
                    $svg[] = "      <rect x=\"" . ($x + $w - 18) . "\" y=\"" . ($y + 4) . "\" width=\"14\" height=\"" . ($h - 8) . "\" rx=\"4\" stroke=\"#92400e\" stroke-width=\"1.5\" fill=\"#fde68a\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'kitchen': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 150;
                    $h = $data['height'] ?? 60;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"3\" stroke=\"#334155\" stroke-width=\"2\" fill=\"#f1f5f9\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 8) . "\" y=\"" . ($y + 8) . "\" width=\"54\" height=\"" . ($h - 16) . "\" rx=\"4\" stroke=\"#0284c7\" stroke-width=\"1.5\" fill=\"#e0f2fe\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 75) . "\" y=\"" . ($y + 8) . "\" width=\"65\" height=\"" . ($h - 16) . "\" rx=\"4\" stroke=\"#b91c1c\" stroke-width=\"1.5\" fill=\"#fee2e2\"/>";
                    $svg[] = "      <circle cx=\"" . ($x + 92) . "\" cy=\"" . ($y + $h / 2) . "\" r=\"12\" stroke=\"#dc2626\" stroke-width=\"1.5\" fill=\"none\"/>";
                    $svg[] = "      <circle cx=\"" . ($x + 123) . "\" cy=\"" . ($y + $h / 2) . "\" r=\"12\" stroke=\"#dc2626\" stroke-width=\"1.5\" fill=\"none\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'washbasin': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 50;
                    $h = $data['height'] ?? 40;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"6\" stroke=\"#0369a1\" stroke-width=\"2\" fill=\"#f0f9ff\"/>";
                    $svg[] = "      <ellipse cx=\"" . ($x + $w / 2) . "\" cy=\"" . ($y + $h / 2 + 2) . "\" rx=\"" . ($w / 2 - 8) . "\" ry=\"" . ($h / 2 - 8) . "\" stroke=\"#0284c7\" stroke-width=\"1.5\" fill=\"#e0f2fe\"/>";
                    $svg[] = "      <circle cx=\"" . ($x + $w / 2) . "\" cy=\"" . ($y + $h / 2 + 3) . "\" r=\"2.5\" fill=\"#0369a1\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'shower': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 80;
                    $h = $data['height'] ?? 80;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" rx=\"4\" stroke=\"#0891b2\" stroke-width=\"2\" fill=\"#ecfeff\"/>";
                    $svg[] = "      <circle cx=\"" . ($x + 16) . "\" cy=\"" . ($y + 16) . "\" r=\"6\" stroke=\"#0891b2\" stroke-width=\"2\" fill=\"#cffafe\"/>";
                    $svg[] = "      <circle cx=\"" . ($x + $w - 18) . "\" cy=\"" . ($y + $h - 18) . "\" r=\"3.5\" fill=\"#0891b2\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'car': {
                    $x = ($data['x'] ?? 0) - $paperX;
                    $y = ($data['y'] ?? 0) - $paperY;
                    $w = $data['width'] ?? 160;
                    $h = $data['height'] ?? 75;

                    $svg[] = "    <g>";
                    $svg[] = "      <rect x=\"" . ($x + 8) . "\" y=\"" . ($y + 6) . "\" width=\"" . ($w - 16) . "\" height=\"" . ($h - 12) . "\" rx=\"16\" stroke=\"#334155\" stroke-width=\"2\" fill=\"#e2e8f0\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 56) . "\" y=\"" . ($y + 15) . "\" width=\"" . ($w - 104) . "\" height=\"" . ($h - 30) . "\" rx=\"4\" stroke=\"#475569\" stroke-width=\"1.5\" fill=\"#ffffff\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 26) . "\" y=\"{$y}\" width=\"22\" height=\"7\" rx=\"2\" fill=\"#0f172a\"/>";
                    $svg[] = "      <rect x=\"" . ($x + 26) . "\" y=\"" . ($y + $h - 7) . "\" width=\"22\" height=\"7\" rx=\"2\" fill=\"#0f172a\"/>";
                    $svg[] = "      <rect x=\"" . ($x + $w - 46) . "\" y=\"{$y}\" width=\"22\" height=\"7\" rx=\"2\" fill=\"#0f172a\"/>";
                    $svg[] = "      <rect x=\"" . ($x + $w - 46) . "\" y=\"" . ($y + $h - 7) . "\" width=\"22\" height=\"7\" rx=\"2\" fill=\"#0f172a\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'tree': {
                    $cx = ($data['x'] ?? 0) - $paperX;
                    $cy = ($data['y'] ?? 0) - $paperY;
                    $r = $data['radius'] ?? 40;

                    $svg[] = "    <g>";
                    $svg[] = "      <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" stroke=\"#16a34a\" stroke-width=\"2\" stroke-dasharray=\"6,4\" fill=\"#dcfce7\"/>";
                    $svg[] = "      <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"" . ($r * 0.7) . "\" stroke=\"#22c55e\" stroke-width=\"1.5\" fill=\"none\"/>";
                    $svg[] = "      <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"4\" fill=\"#14532d\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'north_arrow': {
                    $cx = ($data['x'] ?? 0) - $paperX;
                    $cy = ($data['y'] ?? 0) - $paperY;
                    $r = 30;

                    $svg[] = "    <g>";
                    $svg[] = "      <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" stroke=\"#0f172a\" stroke-width=\"2\" fill=\"#ffffff\"/>";
                    $svg[] = "      <polygon points=\"{$cx}," . ($cy - $r + 3) . " " . ($cx - 9) . "," . ($cy + 4) . " {$cx},{$cy}\" fill=\"#0f172a\" stroke=\"#0f172a\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <polygon points=\"{$cx}," . ($cy - $r + 3) . " " . ($cx + 9) . "," . ($cy + 4) . " {$cx},{$cy}\" fill=\"none\" stroke=\"#0f172a\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <line x1=\"{$cx}\" y1=\"{$cy}\" x2=\"{$cx}\" y2=\"" . ($cy + $r - 5) . "\" stroke=\"#64748b\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <text x=\"{$cx}\" y=\"" . ($cy - $r - 6) . "\" font-size=\"14\" font-weight=\"bold\" fill=\"#0f172a\" text-anchor=\"middle\">U</text>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'elevation_marker': {
                    $cx = ($data['x'] ?? 0) - $paperX;
                    $cy = ($data['y'] ?? 0) - $paperY;
                    $r = 16;
                    $elevText = htmlspecialchars($data['text'] ?? '±0.00', ENT_XML1);

                    $svg[] = "    <g>";
                    $svg[] = "      <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" stroke=\"#0f172a\" stroke-width=\"1.5\" fill=\"#ffffff\"/>";
                    $svg[] = "      <path d=\"M {$cx} {$cy} L " . ($cx - $r) . " {$cy} A {$r} {$r} 0 0 1 {$cx} " . ($cy - $r) . " Z\" fill=\"#0f172a\"/>";
                    $svg[] = "      <path d=\"M {$cx} {$cy} L " . ($cx + $r) . " {$cy} A {$r} {$r} 0 0 1 {$cx} " . ($cy + $r) . " Z\" fill=\"#0f172a\"/>";
                    $svg[] = "      <line x1=\"" . ($cx - $r - 4) . "\" y1=\"{$cy}\" x2=\"" . ($cx + $r + 4) . "\" y2=\"{$cy}\" stroke=\"#0f172a\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <line x1=\"{$cx}\" y1=\"" . ($cy - $r - 4) . "\" x2=\"{$cx}\" y2=\"" . ($cy + $r + 4) . "\" stroke=\"#0f172a\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <text x=\"" . ($cx + $r + 8) . "\" y=\"" . ($cy + 4) . "\" font-family=\"monospace\" font-size=\"13\" font-weight=\"bold\" fill=\"#0f172a\">{$elevText}</text>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'section_marker': {
                    $x1 = ($data['x1'] ?? 0) - $paperX;
                    $y1 = ($data['y1'] ?? 0) - $paperY;
                    $x2 = ($data['x2'] ?? 200) - $paperX;
                    $y2 = ($data['y2'] ?? 0) - $paperY;
                    $label = htmlspecialchars($data['text'] ?? 'A', ENT_XML1);

                    $svg[] = "    <g>";
                    $svg[] = "      <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"#7c3aed\" stroke-width=\"3\" stroke-dasharray=\"16,4,3,4\"/>";
                    $svg[] = "      <circle cx=\"{$x1}\" cy=\"{$y1}\" r=\"16\" stroke=\"#7c3aed\" stroke-width=\"2\" fill=\"#f5f3ff\"/>";
                    $svg[] = "      <text x=\"{$x1}\" y=\"" . ($y1 + 5) . "\" text-anchor=\"middle\" font-size=\"14\" font-weight=\"bold\" fill=\"#6d28d9\">{$label}</text>";
                    $svg[] = "      <circle cx=\"{$x2}\" cy=\"{$y2}\" r=\"16\" stroke=\"#7c3aed\" stroke-width=\"2\" fill=\"#f5f3ff\"/>";
                    $svg[] = "      <text x=\"{$x2}\" y=\"" . ($y2 + 5) . "\" text-anchor=\"middle\" font-size=\"14\" font-weight=\"bold\" fill=\"#6d28d9\">{$label}</text>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'grid_line': {
                    $x1 = ($data['x1'] ?? 0) - $paperX;
                    $y1 = ($data['y1'] ?? 0) - $paperY;
                    $x2 = ($data['x2'] ?? 0) - $paperX;
                    $y2 = ($data['y2'] ?? 200) - $paperY;
                    $code = htmlspecialchars($data['code'] ?? '1', ENT_XML1);

                    $svg[] = "    <g>";
                    $svg[] = "      <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"#0284c7\" stroke-width=\"1.5\" stroke-dasharray=\"12,4,2,4\"/>";
                    $svg[] = "      <circle cx=\"{$x2}\" cy=\"{$y2}\" r=\"14\" stroke=\"#0284c7\" stroke-width=\"2\" fill=\"#ffffff\"/>";
                    $svg[] = "      <text x=\"{$x2}\" y=\"" . ($y2 + 4) . "\" text-anchor=\"middle\" font-size=\"12\" font-weight=\"bold\" fill=\"#0284c7\">{$code}</text>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'leader_note': {
                    $x1 = ($data['x1'] ?? 0) - $paperX;
                    $y1 = ($data['y1'] ?? 0) - $paperY;
                    $x2 = ($data['x2'] ?? 60) - $paperX;
                    $y2 = ($data['y2'] ?? -40) - $paperY;
                    $noteText = htmlspecialchars($data['text'] ?? 'Catatan Spesifikasi', ENT_XML1);
                    $shoulderDir = $x2 >= $x1 ? 1 : -1;
                    $shoulderLen = 45;
                    $shoulderEndX = $x2 + ($shoulderDir * $shoulderLen);
                    $textX = $shoulderDir > 0 ? $x2 + 4 : $shoulderEndX - 4;
                    $anchor = $shoulderDir > 0 ? 'start' : 'end';

                    $svg[] = "    <g>";
                    $svg[] = "      <line x1=\"{$x2}\" y1=\"{$y2}\" x2=\"{$x1}\" y2=\"{$y1}\" stroke=\"#d97706\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <line x1=\"{$x2}\" y1=\"{$y2}\" x2=\"{$shoulderEndX}\" y2=\"{$y2}\" stroke=\"#d97706\" stroke-width=\"1.5\"/>";
                    $svg[] = "      <text x=\"{$textX}\" y=\"" . ($y2 - 4) . "\" text-anchor=\"{$anchor}\" font-size=\"11\" font-weight=\"bold\" fill=\"#92400e\">{$noteText}</text>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'circle': {
                    $cx = ($data['x'] ?? 0) - $paperX;
                    $cy = ($data['y'] ?? 0) - $paperY;
                    $r = $data['radius'] ?? 40;
                    $stroke = $data['stroke'] ?? '#1e293b';
                    $fill = $data['fill'] ?? 'rgba(241, 245, 249, 0.4)';

                    $svg[] = "    <g>";
                    $svg[] = "      <circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" stroke=\"{$stroke}\" stroke-width=\"2\" fill=\"{$fill}\"/>";
                    $svg[] = "      <line x1=\"" . ($cx - 5) . "\" y1=\"{$cy}\" x2=\"" . ($cx + 5) . "\" y2=\"{$cy}\" stroke=\"#64748b\" stroke-width=\"1\"/>";
                    $svg[] = "      <line x1=\"{$cx}\" y1=\"" . ($cy - 5) . "\" x2=\"{$cx}\" y2=\"" . ($cy + 5) . "\" stroke=\"#64748b\" stroke-width=\"1\"/>";
                    $svg[] = "    </g>";
                    break;
                }

                case 'arc': {
                    $cx = ($data['x'] ?? 0) - $paperX;
                    $cy = ($data['y'] ?? 0) - $paperY;
                    $r = $data['radius'] ?? 50;
                    $angle = deg2rad($data['angle'] ?? 90);
                    $rot = deg2rad($data['rotation'] ?? 0);
                    $x1 = $cx + $r * cos($rot);
                    $y1 = $cy + $r * sin($rot);
                    $x2 = $cx + $r * cos($rot + $angle);
                    $y2 = $cy + $r * sin($rot + $angle);
                    $largeArc = ($data['angle'] ?? 90) > 180 ? 1 : 0;

                    $svg[] = "    <path d=\"M {$x1} {$y1} A {$r} {$r} 0 {$largeArc} 1 {$x2} {$y2}\" stroke=\"#1e293b\" stroke-width=\"2\" fill=\"none\"/>";
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
