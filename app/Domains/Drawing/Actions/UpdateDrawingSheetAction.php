<?php

namespace App\Domains\Drawing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Services\DrawingAccessService;

class UpdateDrawingSheetAction
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(User $user, DrawingSheet $sheet, array $data): DrawingSheet
    {
        $this->accessService->authorizeEdit($user, $sheet->project);

        $sheet->update(array_filter([
            'title'        => $data['title'] ?? null,
            'type'         => $data['type'] ?? null,
            'scale'        => $data['scale'] ?? null,
            'paper_size'   => $data['paper_size'] ?? null,
            'orientation'  => $data['orientation'] ?? null,
            'width'        => isset($data['width']) ? (int) $data['width'] : null,
            'height'       => isset($data['height']) ? (int) $data['height'] : null,
            'grid_spacing' => isset($data['grid_spacing']) ? (int) $data['grid_spacing'] : null,
            'unit'         => $data['unit'] ?? null,
            'sort_order'   => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
        ], fn ($val) => ! is_null($val)));

        return $sheet->fresh();
    }
}

