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
    public function execute(User $user, DrawingSheet $sheet, ?string $rawSvg = null): StreamedResponse
    {
        $this->accessService->authorizeView($user, $sheet->project, $sheet);

        $svgContent = $rawSvg ?: $this->buildSvgFromElements($sheet);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $sheet->title) . '.svg';

        return response()->streamDownload(function () use ($svgContent) {
            echo $svgContent;
        }, $filename, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    protected function buildSvgFromElements(DrawingSheet $sheet): string
    {
        $width = $sheet->width ?: ($sheet->orientation === 'portrait' ? 891 : 1260);
        $height = $sheet->height ?: ($sheet->orientation === 'portrait' ? 1260 : 891);

        $svg = [];
        $svg[] = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>";
        $svg[] = "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 {$width} {$height}\" width=\"{$width}\" height=\"{$height}\">";
        $svg[] = "  <rect width=\"100%\" height=\"100%\" fill=\"#ffffff\"/>";

        $elements = $sheet->elements()->get();

        foreach ($elements as $el) {
            $data = is_array($el->data) ? $el->data : json_decode($el->data ?? '[]', true);
            $type = $el->type;

            switch ($type) {
                case 'wall':
                case 'line':
                    $x1 = $data['x1'] ?? $data['x'] ?? 0;
                    $y1 = $data['y1'] ?? $data['y'] ?? 0;
                    $x2 = $data['x2'] ?? ($data['x'] ?? 0) + ($data['width'] ?? 0);
                    $y2 = $data['y2'] ?? ($data['y'] ?? 0) + ($data['height'] ?? 0);
                    $stroke = $type === 'wall' ? '#1e293b' : ($data['stroke'] ?? '#64748b');
                    $strokeWidth = $type === 'wall' ? ($data['strokeWidth'] ?? 6) : ($data['strokeWidth'] ?? 2);
                    $svg[] = "  <line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"{$stroke}\" stroke-width=\"{$strokeWidth}\" stroke-linecap=\"round\"/>";
                    break;

                case 'rect':
                    $x = $data['x'] ?? 0;
                    $y = $data['y'] ?? 0;
                    $w = $data['width'] ?? 100;
                    $h = $data['height'] ?? 100;
                    $stroke = $data['stroke'] ?? '#1e293b';
                    $fill = $data['fill'] ?? 'none';
                    $svg[] = "  <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" stroke=\"{$stroke}\" stroke-width=\"2\" fill=\"{$fill}\"/>";
                    break;

                case 'door':
                case 'window':
                    $x = $data['x'] ?? 0;
                    $y = $data['y'] ?? 0;
                    $w = $data['width'] ?? 80;
                    $h = $data['height'] ?? 20;
                    $stroke = $type === 'door' ? '#16a34a' : '#0284c7';
                    $fill = $type === 'door' ? '#dcfce7' : '#e0f2fe';
                    $svg[] = "  <rect x=\"{$x}\" y=\"{$y}\" width=\"{$w}\" height=\"{$h}\" stroke=\"{$stroke}\" stroke-width=\"2\" fill=\"{$fill}\"/>";
                    break;

                case 'text':
                case 'room_label':
                    $x = $data['x'] ?? 0;
                    $y = $data['y'] ?? 0;
                    $text = htmlspecialchars($data['text'] ?? 'Label', ENT_XML1);
                    $fontSize = $data['fontSize'] ?? 14;
                    $fill = $data['fill'] ?? '#0f172a';
                    $svg[] = "  <text x=\"{$x}\" y=\"{$y}\" font-family=\"sans-serif\" font-size=\"{$fontSize}\" fill=\"{$fill}\">{$text}</text>";
                    break;
            }
        }

        $svg[] = "</svg>";

        return implode("\n", $svg);
    }
}

