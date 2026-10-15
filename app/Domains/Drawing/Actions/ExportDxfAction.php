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
    public function execute(User $user, DrawingSheet $sheet): StreamedResponse
    {
        $this->accessService->authorizeView($user, $sheet->project, $sheet);

        $elements = $sheet->elements()->get();

        $dxfContent = $this->buildDxfContent($sheet, $elements);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $sheet->title) . '.dxf';

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
    protected function buildDxfContent(DrawingSheet $sheet, $elements): string
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
        $out[] = "70\n5";

        $layers = [
            '0'          => 7, // White
            'WALLS'      => 1, // Red
            'DOORS'      => 3, // Green
            'WINDOWS'    => 4, // Cyan
            'STRUCTURE'  => 6, // Magenta
            'DIMENSIONS' => 2, // Yellow
            'TEXT'       => 7, // White
            'FURNITURE'  => 5, // Blue
        ];

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

        foreach ($elements as $el) {
            $data = is_array($el->data) ? $el->data : json_decode($el->data ?? '[]', true);
            $type = $el->type;

            // Invert Y coordinate because CAD origin (0,0) is bottom-left, while Canvas is top-left
            $scaleFactor = 0.01; // Canvas pixels to meters

            switch ($type) {
                case 'wall':
                case 'line':
                    $x1 = ($data['x1'] ?? $data['x'] ?? 0) * $scaleFactor;
                    $y1 = -($data['y1'] ?? $data['y'] ?? 0) * $scaleFactor;
                    $x2 = ($data['x2'] ?? ($data['x'] ?? 0) + ($data['width'] ?? 0)) * $scaleFactor;
                    $y2 = -($data['y2'] ?? ($data['y'] ?? 0) + ($data['height'] ?? 0)) * $scaleFactor;
                    $layer = $type === 'wall' ? 'WALLS' : '0';

                    $out[] = "0\nLINE";
                    $out[] = "8\n{$layer}";
                    $out[] = "10\n{$x1}";
                    $out[] = "20\n{$y1}";
                    $out[] = "30\n0.0";
                    $out[] = "11\n{$x2}";
                    $out[] = "21\n{$y2}";
                    $out[] = "31\n0.0";
                    break;

                case 'column':
                    $x = ($data['x'] ?? 0) * $scaleFactor;
                    $y = -($data['y'] ?? 0) * $scaleFactor;
                    $size = ($data['size'] ?? 30) * $scaleFactor;

                    // Box + cross hatch lines
                    $pts = [
                        [$x, $y, $x + $size, $y],
                        [$x + $size, $y, $x + $size, $y - $size],
                        [$x + $size, $y - $size, $x, $y - $size],
                        [$x, $y - $size, $x, $y],
                        [$x, $y, $x + $size, $y - $size],
                        [$x, $y - $size, $x + $size, $y],
                    ];
                    foreach ($pts as $pt) {
                        $out[] = "0\nLINE";
                        $out[] = "8\nSTRUCTURE";
                        $out[] = "10\n{$pt[0]}";
                        $out[] = "20\n{$pt[1]}";
                        $out[] = "30\n0.0";
                        $out[] = "11\n{$pt[2]}";
                        $out[] = "21\n{$pt[3]}";
                        $out[] = "31\n0.0";
                    }
                    break;

                case 'stair':
                    $x = ($data['x'] ?? 0) * $scaleFactor;
                    $y = -($data['y'] ?? 0) * $scaleFactor;
                    $w = ($data['width'] ?? 80) * $scaleFactor;
                    $h = ($data['height'] ?? 160) * $scaleFactor;
                    $steps = 8;
                    $stepH = $h / $steps;

                    // Outline
                    $out[] = "0\nLINE\n8\nSTRUCTURE\n10\n{$x}\n20\n{$y}\n30\n0.0\n11\n" . ($x + $w) . "\n21\n{$y}\n31\n0.0";
                    $out[] = "0\nLINE\n8\nSTRUCTURE\n10\n" . ($x + $w) . "\n20\n{$y}\n30\n0.0\n11\n" . ($x + $w) . "\n21\n" . ($y - $h) . "\n31\n0.0";
                    $out[] = "0\nLINE\n8\nSTRUCTURE\n10\n" . ($x + $w) . "\n20\n" . ($y - $h) . "\n30\n0.0\n11\n{$x}\n21\n" . ($y - $h) . "\n31\n0.0";
                    $out[] = "0\nLINE\n8\nSTRUCTURE\n10\n{$x}\n20\n" . ($y - $h) . "\n30\n0.0\n11\n{$x}\n21\n{$y}\n31\n0.0";

                    // Step lines
                    for ($s = 1; $s < $steps; $s++) {
                        $sy = $y - ($s * $stepH);
                        $out[] = "0\nLINE\n8\nSTRUCTURE\n10\n{$x}\n20\n{$sy}\n30\n0.0\n11\n" . ($x + $w) . "\n21\n{$sy}\n31\n0.0";
                    }
                    break;

                case 'toilet':
                case 'bed':
                case 'table':
                    $x = ($data['x'] ?? 0) * $scaleFactor;
                    $y = -($data['y'] ?? 0) * $scaleFactor;
                    $w = ($data['width'] ?? 60) * $scaleFactor;
                    $h = ($data['height'] ?? 60) * $scaleFactor;

                    $pts = [
                        [$x, $y, $x + $w, $y],
                        [$x + $w, $y, $x + $w, $y - $h],
                        [$x + $w, $y - $h, $x, $y - $h],
                        [$x, $y - $h, $x, $y],
                    ];
                    foreach ($pts as $pt) {
                        $out[] = "0\nLINE";
                        $out[] = "8\nFURNITURE";
                        $out[] = "10\n{$pt[0]}";
                        $out[] = "20\n{$pt[1]}";
                        $out[] = "30\n0.0";
                        $out[] = "11\n{$pt[2]}";
                        $out[] = "21\n{$pt[3]}";
                        $out[] = "31\n0.0";
                    }
                    break;

                case 'rect':
                    $x = ($data['x'] ?? 0) * $scaleFactor;
                    $y = -($data['y'] ?? 0) * $scaleFactor;
                    $w = ($data['width'] ?? 100) * $scaleFactor;
                    $h = ($data['height'] ?? 100) * $scaleFactor;

                    // 4 lines for rect
                    $points = [
                        [$x, $y, $x + $w, $y],
                        [$x + $w, $y, $x + $w, $y - $h],
                        [$x + $w, $y - $h, $x, $y - $h],
                        [$x, $y - $h, $x, $y],
                    ];

                    foreach ($points as $pt) {
                        $out[] = "0\nLINE";
                        $out[] = "8\n0";
                        $out[] = "10\n{$pt[0]}";
                        $out[] = "20\n{$pt[1]}";
                        $out[] = "30\n0.0";
                        $out[] = "11\n{$pt[2]}";
                        $out[] = "21\n{$pt[3]}";
                        $out[] = "31\n0.0";
                    }
                    break;

                case 'door':
                case 'window':
                    $x = ($data['x'] ?? 0) * $scaleFactor;
                    $y = -($data['y'] ?? 0) * $scaleFactor;
                    $w = ($data['width'] ?? 80) * $scaleFactor;
                    $h = ($data['height'] ?? 20) * $scaleFactor;
                    $layer = $type === 'door' ? 'DOORS' : 'WINDOWS';

                    $out[] = "0\nLINE";
                    $out[] = "8\n{$layer}";
                    $out[] = "10\n{$x}";
                    $out[] = "20\n{$y}";
                    $out[] = "30\n0.0";
                    $out[] = "11\n" . ($x + $w);
                    $out[] = "21\n" . ($y - $h);
                    $out[] = "31\n0.0";
                    break;

                case 'text':
                case 'room_label':
                    $x = ($data['x'] ?? 0) * $scaleFactor;
                    $y = -($data['y'] ?? 0) * $scaleFactor;
                    $text = $data['text'] ?? 'Label';
                    $fontSize = (($data['fontSize'] ?? 14) * 0.5) * $scaleFactor;

                    $out[] = "0\nTEXT";
                    $out[] = "8\nTEXT";
                    $out[] = "10\n{$x}";
                    $out[] = "20\n{$y}";
                    $out[] = "30\n0.0";
                    $out[] = "40\n{$fontSize}";
                    $out[] = "1\n{$text}";
                    break;

                case 'dimension':
                    $x1 = ($data['x1'] ?? 0) * $scaleFactor;
                    $y1 = -($data['y1'] ?? 0) * $scaleFactor;
                    $x2 = ($data['x2'] ?? 0) * $scaleFactor;
                    $y2 = -($data['y2'] ?? 0) * $scaleFactor;

                    $out[] = "0\nLINE";
                    $out[] = "8\nDIMENSIONS";
                    $out[] = "10\n{$x1}";
                    $out[] = "20\n{$y1}";
                    $out[] = "30\n0.0";
                    $out[] = "11\n{$x2}";
                    $out[] = "21\n{$y2}";
                    $out[] = "31\n0.0";
                    break;
            }
        }

        $out[] = "0\nENDSEC";
        $out[] = "0\nEOF\n";

        return implode("\n", $out);
    }
}

