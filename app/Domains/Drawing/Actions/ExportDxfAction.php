<?php

namespace App\Domains\Drawing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Services\DrawingAccessService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDxfAction
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    /**
     * Export DrawingSheet elements to standard AutoCAD DXF format (R12 ASCII).
     */
    public function execute(User $user, DrawingSheet $sheet, ?string $frameId = null): StreamedResponse
    {
        $this->accessService->authorizeView($user, $sheet->project, $sheet);

        $elements = $sheet->elements()->orderBy('sort_order')->get();

        $targetTitle = $sheet->title;
        if ($frameId && $frameId !== 'main_frame') {
            $frameEl = $elements->firstWhere('id', $frameId);
            if ($frameEl && $frameEl->type === 'paper_frame') {
                $fData = is_array($frameEl->data) ? $frameEl->data : json_decode($frameEl->data ?? '[]', true);
                if (!empty($fData['title'])) {
                    $targetTitle = $fData['title'];
                }
            }
        }

        $dxfContent = $this->buildDxfContent($sheet, $elements, $frameId);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $targetTitle) . '.dxf';

        return response()->streamDownload(function () use ($dxfContent) {
            echo $dxfContent;
        }, $filename, [
            'Content-Type'        => 'application/dxf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Bangun string file DXF compliant AutoCAD R12 ASCII.
     */
    protected function buildDxfContent(DrawingSheet $sheet, $elements, ?string $frameId = null): string
    {
        $out = [];

        // Header Section
        $out[] = "0\nSECTION";
        $out[] = "2\nHEADER";
        $out[] = "9\n\$ACADVER";
        $out[] = "1\nAC1009"; // AutoCAD R12 standard compatibility
        $out[] = "9\n\$INSUNITS";
        $out[] = "70\n6"; // Meter
        $out[] = "0\nENDSEC";

        // Tables Section (Layers)
        $out[] = "0\nSECTION";
        $out[] = "2\nTABLES";
        $out[] = "0\nTABLE";
        $out[] = "2\nLAYER";
        $layers = [
            '0'           => 7, // White (Default)
            'BORDER'      => 7, // White (Border Kertas)
            'TITLE_BLOCK' => 7, // White (Kop Gambar)
            'WALLS'       => 1, // Red (Dinding)
            'DOORS'       => 3, // Green (Pintu)
            'WINDOWS'     => 4, // Cyan (Jendela)
            'STRUCTURE'   => 6, // Magenta (Kolom & Tangga)
            'DIMENSIONS'  => 2, // Yellow (Dimensi Ukuran)
            'TEXT'        => 7, // White (Teks & Label)
            'FURNITURE'   => 5, // Blue (Furnitur)
        ];

        $out[] = "70\n" . count($layers);

        foreach ($layers as $layerName => $color) {
            $out[] = "0\nLAYER";
            $out[] = "2\n{$layerName}";
            $out[] = "70\n0";
            $out[] = "62\n{$color}";
            $out[] = "6\nCONTINUOUS";
        }

        $out[] = "0\nENDTAB";
        $out[] = "0\nENDSEC";

        // Entities Section
        $out[] = "0\nSECTION";
        $out[] = "2\nENTITIES";

        $scaleFactor = 0.01; // Canvas pixels to meters
        $paperX = 60;
        $paperY = 60;
        $rawWidth = $sheet->width ?: ($sheet->orientation === 'portrait' ? 891 : 1260);
        $rawHeight = $sheet->height ?: ($sheet->orientation === 'portrait' ? 1260 : 891);
        $sheetTitle = $sheet->title;

        if ($frameId && $frameId !== 'main_frame') {
            $frameEl = $elements->firstWhere('id', $frameId);
            if ($frameEl && $frameEl->type === 'paper_frame') {
                $fData = is_array($frameEl->data) ? $frameEl->data : json_decode($frameEl->data ?? '[]', true);
                $paperX = (float) ($fData['x'] ?? 60);
                $paperY = (float) ($fData['y'] ?? 60);
                $rawWidth = (float) ($fData['width'] ?? $rawWidth);
                $rawHeight = (float) ($fData['height'] ?? $rawHeight);
                if (!empty($fData['title'])) {
                    $sheetTitle = $fData['title'];
                }
            }
        }

        $width = $rawWidth * $scaleFactor;
        $height = $rawHeight * $scaleFactor;

        // 1. Gambar Border Kertas & Margin Frame
        // Border Luar Kertas (0,0) ke (width, height)
        $this->addDxfRect($out, 'BORDER', 0, 0, $width, -$height);

        // Margin Frame (20px = 0.20m)
        $margin = 20 * $scaleFactor;
        $this->addDxfRect($out, 'BORDER', $margin, -$margin, $width - $margin * 2, -($height - $margin * 2));

        // Kop Title Block di Pojok Kanan Bawah
        $kopW = min(320 * $scaleFactor, $width - 0.6);
        $kopH = 80 * $scaleFactor;
        $kopX = $width - $kopW - $margin;
        $kopY = -($height - $kopH - $margin);

        $this->addDxfRect($out, 'TITLE_BLOCK', $kopX, $kopY + $kopH, $kopW, -$kopH);
        $this->addDxfText($out, 'TITLE_BLOCK', $kopX + 0.1, $kopY + $kopH - 0.18, 0.12, 'STUDIO ARSITEKTUR & DESAIN');
        $this->addDxfText($out, 'TITLE_BLOCK', $kopX + 0.1, $kopY + $kopH - 0.36, 0.15, $sheetTitle);
        $this->addDxfText($out, 'TITLE_BLOCK', $kopX + 0.1, $kopY + $kopH - 0.54, 0.11, 'Proyek: ' . ($sheet->project->title ?? ''));
        $this->addDxfText($out, 'TITLE_BLOCK', $kopX + 0.1, $kopY + $kopH - 0.70, 0.09, 'Skala: ' . $sheet->scale . ' | ' . ($sheet->paper_size ?? 'A3'));

        // 2. Gambar Seluruh Elemen Gambar Kerja
        foreach ($elements as $el) {
            if ($el->type === 'paper_frame') {
                continue;
            }

            $data = is_array($el->data) ? $el->data : json_decode($el->data ?? '[]', true);
            $type = $el->type;

            switch ($type) {
                case 'wall': {
                    $x1 = (($data['x1'] ?? $data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y1 = -(($data['y1'] ?? $data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $x2 = ((($data['x2'] ?? ($data['x'] ?? 0) + ($data['width'] ?? 0))) - $paperX) * $scaleFactor;
                    $y2 = -(($data['y2'] ?? ($data['y'] ?? 0) + ($data['height'] ?? 0)) - $paperY) * $scaleFactor;
                    $thickness = (float) ($data['thickness'] ?? 12) * $scaleFactor;

                    // Hitung vektor normal untuk dinding ganda sejajar
                    $dx = $x2 - $x1;
                    $dy = $y2 - $y1;
                    $len = sqrt($dx * $dx + $dy * $dy);
                    if ($len > 0.001) {
                        $nx = (-$dy / $len) * ($thickness / 2);
                        $ny = ($dx / $len) * ($thickness / 2);

                        // Garis luar dinding kiri & kanan
                        $this->addDxfLine($out, 'WALLS', $x1 + $nx, $y1 + $ny, $x2 + $nx, $y2 + $ny);
                        $this->addDxfLine($out, 'WALLS', $x1 - $nx, $y1 - $ny, $x2 - $nx, $y2 - $ny);
                        // Garis tutup ujung dinding
                        $this->addDxfLine($out, 'WALLS', $x1 + $nx, $y1 + $ny, $x1 - $nx, $y1 - $ny);
                        $this->addDxfLine($out, 'WALLS', $x2 + $nx, $y2 + $ny, $x2 - $nx, $y2 - $ny);
                    }

                    // Garis sumbu as tengah (Layer 0)
                    $this->addDxfLine($out, '0', $x1, $y1, $x2, $y2);
                    break;
                }

                case 'line': {
                    $x1 = (($data['x1'] ?? $data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y1 = -(($data['y1'] ?? $data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $x2 = ((($data['x2'] ?? ($data['x'] ?? 0) + ($data['width'] ?? 0))) - $paperX) * $scaleFactor;
                    $y2 = -(($data['y2'] ?? ($data['y'] ?? 0) + ($data['height'] ?? 0)) - $paperY) * $scaleFactor;
                    $this->addDxfLine($out, '0', $x1, $y1, $x2, $y2);
                    break;
                }

                case 'column': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $size = ($data['size'] ?? 30) * $scaleFactor;

                    // Kotak kolom struktur
                    $this->addDxfRect($out, 'STRUCTURE', $x, $y, $size, -$size);
                    // Arsir silang beton
                    $this->addDxfLine($out, 'STRUCTURE', $x, $y, $x + $size, $y - $size);
                    $this->addDxfLine($out, 'STRUCTURE', $x, $y - $size, $x + $size, $y);
                    break;
                }

                case 'door': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $size = ($data['size'] ?? 80) * $scaleFactor;

                    // Kusen / daun pintu
                    $this->addDxfLine($out, 'DOORS', $x, $y, $x, $y - $size);

                    // Busur ayunan pintu (16-segmen polyline kurva)
                    $steps = 16;
                    for ($i = 0; $i < $steps; $i++) {
                        $a1 = ($i / $steps) * (M_PI / 2);
                        $a2 = (($i + 1) / $steps) * (M_PI / 2);

                        $px1 = $x + $size * sin($a1);
                        $py1 = ($y - $size) + $size * cos($a1);
                        $px2 = $x + $size * sin($a2);
                        $py2 = ($y - $size) + $size * cos($a2);

                        $this->addDxfLine($out, 'DOORS', $px1, $py1, $px2, $py2);
                    }
                    break;
                }

                case 'window': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 100) * $scaleFactor;
                    $h = ($data['height'] ?? 16) * $scaleFactor;

                    // Kusen luar jendela
                    $this->addDxfRect($out, 'WINDOWS', $x, $y, $w, -$h);
                    // Garis kaca tengah
                    $this->addDxfLine($out, 'WINDOWS', $x, $y - $h / 2, $x + $w, $y - $h / 2);
                    break;
                }

                case 'stair': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 80) * $scaleFactor;
                    $h = ($data['height'] ?? 160) * $scaleFactor;
                    $steps = 8;
                    $stepH = $h / $steps;

                    // Outline tangga
                    $this->addDxfRect($out, 'STRUCTURE', $x, $y, $w, -$h);
                    // Anak tangga
                    for ($s = 1; $s < $steps; $s++) {
                        $sy = $y - ($s * $stepH);
                        $this->addDxfLine($out, 'STRUCTURE', $x, $sy, $x + $w, $sy);
                    }
                    // Panah arah naik (UP)
                    $arrowX = $x + $w / 2;
                    $this->addDxfLine($out, 'STRUCTURE', $arrowX, $y - $h + 0.15, $arrowX, $y - 0.2);
                    $this->addDxfLine($out, 'STRUCTURE', $arrowX, $y - 0.2, $arrowX - 0.05, $y - 0.3);
                    $this->addDxfLine($out, 'STRUCTURE', $arrowX, $y - 0.2, $arrowX + 0.05, $y - 0.3);
                    $this->addDxfText($out, 'TEXT', $arrowX - 0.08, $y - $h / 2, 0.1, 'UP');
                    break;
                }

                case 'double_door': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 140) * $scaleFactor;
                    $leaf = $w / 2;

                    // Kusen kiri dan kanan
                    $this->addDxfLine($out, 'DOORS', $x, $y, $x, $y - $leaf);
                    $this->addDxfLine($out, 'DOORS', $x + $w, $y, $x + $w, $y - $leaf);

                    $steps = 12;
                    for ($i = 0; $i < $steps; $i++) {
                        $a1 = ($i / $steps) * (M_PI / 2);
                        $a2 = (($i + 1) / $steps) * (M_PI / 2);

                        $px1 = $x + $leaf * sin($a1);
                        $py1 = ($y - $leaf) + $leaf * cos($a1);
                        $px2 = $x + $leaf * sin($a2);
                        $py2 = ($y - $leaf) + $leaf * cos($a2);
                        $this->addDxfLine($out, 'DOORS', $px1, $py1, $px2, $py2);

                        $px3 = ($x + $w) - $leaf * sin($a1);
                        $px4 = ($x + $w) - $leaf * sin($a2);
                        $this->addDxfLine($out, 'DOORS', $px3, $py1, $px4, $py2);
                    }
                    break;
                }

                case 'sliding_door': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 120) * $scaleFactor;
                    $h = ($data['height'] ?? 16) * $scaleFactor;
                    $this->addDxfRect($out, 'DOORS', $x, $y, $w, -$h);
                    $this->addDxfRect($out, 'DOORS', $x + 0.02, $y - 0.02, $w * 0.52, -($h / 2));
                    $this->addDxfRect($out, 'DOORS', $x + $w * 0.46, $y - ($h / 2), $w * 0.52, -($h / 2));
                    break;
                }

                case 'sofa': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 140) * $scaleFactor;
                    $h = ($data['height'] ?? 65) * $scaleFactor;
                    $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -$h);
                    $this->addDxfRect($out, 'FURNITURE', $x + 0.06, $y - 0.04, $w - 0.12, -0.14);
                    break;
                }

                case 'kitchen': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 150) * $scaleFactor;
                    $h = ($data['height'] ?? 60) * $scaleFactor;
                    $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -$h);
                    // Sink
                    $this->addDxfRect($out, 'FURNITURE', $x + 0.08, $y - 0.08, 0.54, -($h - 0.16));
                    // Stove
                    $this->addDxfRect($out, 'FURNITURE', $x + 0.75, $y - 0.08, 0.65, -($h - 0.16));
                    break;
                }

                case 'washbasin': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 50) * $scaleFactor;
                    $h = ($data['height'] ?? 40) * $scaleFactor;
                    $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -$h);
                    $this->addDxfRect($out, 'FURNITURE', $x + 0.05, $y - 0.05, $w - 0.1, -($h - 0.1));
                    break;
                }

                case 'shower': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 80) * $scaleFactor;
                    $h = ($data['height'] ?? 80) * $scaleFactor;
                    $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -$h);
                    $this->addDxfLine($out, 'FURNITURE', $x, $y, $x + $w, $y - $h);
                    $this->addDxfLine($out, 'FURNITURE', $x, $y - $h, $x + $w, $y);
                    break;
                }

                case 'car': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 160) * $scaleFactor;
                    $h = ($data['height'] ?? 75) * $scaleFactor;
                    $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -$h);
                    $this->addDxfLine($out, 'FURNITURE', $x + 0.42, $y - 0.1, $x + 0.56, $y - 0.16);
                    $this->addDxfLine($out, 'FURNITURE', $x + 0.42, $y - $h + 0.1, $x + 0.56, $y - $h + 0.16);
                    $this->addDxfLine($out, 'FURNITURE', $x + 0.56, $y - 0.16, $x + 0.56, $y - $h + 0.16);
                    $this->addDxfText($out, 'TEXT', $x + $w / 2 - 0.15, $y - $h / 2, 0.12, 'CAR');
                    break;
                }

                case 'tree': {
                    $cx = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $cy = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $r = ($data['radius'] ?? 40) * $scaleFactor;
                    $this->addDxfCircle($out, 'FURNITURE', $cx, $cy, $r);
                    $this->addDxfCircle($out, 'FURNITURE', $cx, $cy, $r * 0.7);
                    break;
                }

                case 'north_arrow': {
                    $cx = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $cy = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $r = 30 * $scaleFactor;
                    $this->addDxfCircle($out, 'TEXT', $cx, $cy, $r);
                    $this->addDxfLine($out, 'TEXT', $cx, $cy + $r - 0.03, $cx - 0.09, $cy - 0.04);
                    $this->addDxfLine($out, 'TEXT', $cx, $cy + $r - 0.03, $cx + 0.09, $cy - 0.04);
                    $this->addDxfLine($out, 'TEXT', $cx, $cy + $r - 0.03, $cx, $cy - $r + 0.05);
                    $this->addDxfText($out, 'TEXT', $cx - 0.05, $cy + $r + 0.08, 0.15, 'U');
                    break;
                }

                case 'elevation_marker': {
                    $cx = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $cy = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $r = 16 * $scaleFactor;
                    $text = $data['text'] ?? '±0.00';
                    $this->addDxfCircle($out, 'DIMENSIONS', $cx, $cy, $r);
                    $this->addDxfLine($out, 'DIMENSIONS', $cx - $r - 0.04, $cy, $cx + $r + 0.04, $cy);
                    $this->addDxfLine($out, 'DIMENSIONS', $cx, $cy - $r - 0.04, $cx, $cy + $r + 0.04);
                    $this->addDxfText($out, 'TEXT', $cx + $r + 0.08, $cy, 0.12, $text);
                    break;
                }

                case 'section_marker': {
                    $x1 = (($data['x1'] ?? 0) - $paperX) * $scaleFactor;
                    $y1 = -(($data['y1'] ?? 0) - $paperY) * $scaleFactor;
                    $x2 = (($data['x2'] ?? 200) - $paperX) * $scaleFactor;
                    $y2 = -(($data['y2'] ?? 0) - $paperY) * $scaleFactor;
                    $label = $data['text'] ?? 'A';
                    $r = 16 * $scaleFactor;

                    $this->addDxfLine($out, 'DIMENSIONS', $x1, $y1, $x2, $y2);
                    $this->addDxfCircle($out, 'DIMENSIONS', $x1, $y1, $r);
                    $this->addDxfText($out, 'TEXT', $x1 - 0.05, $y1, 0.12, $label);
                    $this->addDxfCircle($out, 'DIMENSIONS', $x2, $y2, $r);
                    $this->addDxfText($out, 'TEXT', $x2 - 0.05, $y2, 0.12, $label);
                    break;
                }

                case 'grid_line': {
                    $x1 = (($data['x1'] ?? 0) - $paperX) * $scaleFactor;
                    $y1 = -(($data['y1'] ?? 0) - $paperY) * $scaleFactor;
                    $x2 = (($data['x2'] ?? 0) - $paperX) * $scaleFactor;
                    $y2 = -(($data['y2'] ?? 200) - $paperY) * $scaleFactor;
                    $code = $data['code'] ?? '1';
                    $r = 14 * $scaleFactor;

                    $this->addDxfLine($out, '0', $x1, $y1, $x2, $y2);
                    $this->addDxfCircle($out, '0', $x2, $y2, $r);
                    $this->addDxfText($out, 'TEXT', $x2 - 0.05, $y2, 0.12, $code);
                    break;
                }

                case 'leader_note': {
                    $x1 = (($data['x1'] ?? 0) - $paperX) * $scaleFactor;
                    $y1 = -(($data['y1'] ?? 0) - $paperY) * $scaleFactor;
                    $x2 = (($data['x2'] ?? 60) - $paperX) * $scaleFactor;
                    $y2 = -(($data['y2'] ?? -40) - $paperY) * $scaleFactor;
                    $noteText = $data['text'] ?? 'Spesifikasi';
                    $shoulderLen = 45 * $scaleFactor;
                    $shoulderDir = $x2 >= $x1 ? 1 : -1;

                    $this->addDxfLine($out, 'DIMENSIONS', $x1, $y1, $x2, $y2);
                    $this->addDxfLine($out, 'DIMENSIONS', $x2, $y2, $x2 + ($shoulderDir * $shoulderLen), $y2);
                    $this->addDxfText($out, 'TEXT', $x2 + ($shoulderDir > 0 ? 0.04 : -$shoulderLen), $y2 + 0.08, 0.11, $noteText);
                    break;
                }

                case 'circle': {
                    $cx = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $cy = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $r = ($data['radius'] ?? 40) * $scaleFactor;
                    $this->addDxfCircle($out, '0', $cx, $cy, $r);
                    break;
                }

                case 'arc': {
                    $cx = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $cy = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $r = ($data['radius'] ?? 50) * $scaleFactor;
                    $ang = $data['angle'] ?? 90;
                    $rot = $data['rotation'] ?? 0;
                    $steps = 12;
                    for ($i = 0; $i < $steps; $i++) {
                        $a1 = deg2rad($rot + ($i / $steps) * $ang);
                        $a2 = deg2rad($rot + (($i + 1) / $steps) * $ang);
                        $this->addDxfLine($out, '0', $cx + $r * cos($a1), $cy + $r * sin($a1), $cx + $r * cos($a2), $cy + $r * sin($a2));
                    }
                    break;
                }

                case 'toilet':
                case 'bed':
                case 'table': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 60) * $scaleFactor;
                    $h = ($data['height'] ?? 60) * $scaleFactor;

                    $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -$h);

                    if ($type === 'bed') {
                        // Bantal & lipatan selimut
                        $this->addDxfLine($out, 'FURNITURE', $x, $y - 0.45, $x + $w, $y - 0.45);
                    } elseif ($type === 'toilet') {
                        // Tangki air
                        $this->addDxfRect($out, 'FURNITURE', $x, $y, $w, -0.15);
                    }
                    break;
                }

                case 'rect': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $w = ($data['width'] ?? 100) * $scaleFactor;
                    $h = ($data['height'] ?? 100) * $scaleFactor;
                    $this->addDxfRect($out, '0', $x, $y, $w, -$h);
                    break;
                }

                case 'dimension': {
                    $x1 = (($data['x1'] ?? 0) - $paperX) * $scaleFactor;
                    $y1 = -(($data['y1'] ?? 0) - $paperY) * $scaleFactor;
                    $x2 = (($data['x2'] ?? 0) - $paperX) * $scaleFactor;
                    $y2 = -(($data['y2'] ?? 0) - $paperY) * $scaleFactor;

                    // Garis dimensi
                    $this->addDxfLine($out, 'DIMENSIONS', $x1, $y1, $x2, $y2);

                    // Garis centang arsitektur 45 derajat pada kedua ujung
                    $tick = 0.04;
                    $this->addDxfLine($out, 'DIMENSIONS', $x1 - $tick, $y1 - $tick, $x1 + $tick, $y1 + $tick);
                    $this->addDxfLine($out, 'DIMENSIONS', $x2 - $tick, $y2 - $tick, $x2 + $tick, $y2 + $tick);

                    // Teks ukuran (mm)
                    $dx = ($x2 - $x1) / $scaleFactor;
                    $dy = ($y2 - $y1) / $scaleFactor;
                    $mm = round(sqrt($dx * $dx + $dy * $dy) * 10);
                    $midX = ($x1 + $x2) / 2;
                    $midY = (($y1 + $y2) / 2) + 0.05;
                    $this->addDxfText($out, 'DIMENSIONS', $midX, $midY, 0.1, "{$mm} mm");
                    break;
                }

                case 'room_label': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $rawText = $data['text'] ?? 'R. Tamu';
                    $lines = explode("\n", $rawText);
                    foreach ($lines as $i => $lineText) {
                        $this->addDxfText($out, 'TEXT', $x, $y - ($i * 0.16), 0.12, $lineText);
                    }
                    break;
                }

                case 'text': {
                    $x = (($data['x'] ?? 0) - $paperX) * $scaleFactor;
                    $y = -(($data['y'] ?? 0) - $paperY) * $scaleFactor;
                    $text = $data['text'] ?? 'Label';
                    $fontSize = (($data['fontSize'] ?? 14) * 0.5) * $scaleFactor;
                    $this->addDxfText($out, 'TEXT', $x, $y, max(0.08, $fontSize), $text);
                    break;
                }
            }
        }

        $out[] = "0\nENDSEC";
        $out[] = "0\nEOF\n";

        return implode("\n", $out);
    }

    protected function addDxfLine(array &$out, string $layer, float $x1, float $y1, float $x2, float $y2): void
    {
        $out[] = "0\nLINE";
        $out[] = "8\n{$layer}";
        $out[] = "10\n" . sprintf('%.4f', $x1);
        $out[] = "20\n" . sprintf('%.4f', $y1);
        $out[] = "30\n0.0";
        $out[] = "11\n" . sprintf('%.4f', $x2);
        $out[] = "21\n" . sprintf('%.4f', $y2);
        $out[] = "31\n0.0";
    }

    protected function addDxfRect(array &$out, string $layer, float $x, float $y, float $w, float $h): void
    {
        $this->addDxfLine($out, $layer, $x, $y, $x + $w, $y);
        $this->addDxfLine($out, $layer, $x + $w, $y, $x + $w, $y + $h);
        $this->addDxfLine($out, $layer, $x + $w, $y + $h, $x, $y + $h);
        $this->addDxfLine($out, $layer, $x, $y + $h, $x, $y);
    }

    protected function addDxfText(array &$out, string $layer, float $x, float $y, float $size, string $text): void
    {
        $safeText = preg_replace('/[^a-zA-Z0-9_\-\.\:\s\(\)\+\=\,\/]/', '', $text);
        if ($safeText === '') $safeText = 'TEXT';

        $out[] = "0\nTEXT";
        $out[] = "8\n{$layer}";
        $out[] = "10\n" . sprintf('%.4f', $x);
        $out[] = "20\n" . sprintf('%.4f', $y);
        $out[] = "30\n0.0";
        $out[] = "40\n" . sprintf('%.4f', $size);
        $out[] = "1\n{$safeText}";
    }

    protected function addDxfCircle(array &$out, string $layer, float $cx, float $cy, float $radius): void
    {
        $out[] = "0\nCIRCLE";
        $out[] = "8\n{$layer}";
        $out[] = "10\n" . sprintf('%.4f', $cx);
        $out[] = "20\n" . sprintf('%.4f', $cy);
        $out[] = "30\n0.0";
        $out[] = "40\n" . sprintf('%.4f', $radius);
    }
}
