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

        if (! empty($data['project_id']) && $data['project_id'] !== $sheet->project_id) {
            $newProject = \App\Domains\Project\Models\Project::findOrFail($data['project_id']);
            $this->accessService->authorizeEdit($user, $newProject);
            $sheet->project_id = $newProject->id;
        }

        $targetProject = $sheet->project;
        if ($targetProject) {
            $projectUpdates = [];
            if (array_key_exists('client_name', $data)) {
                $projectUpdates['client_name'] = $data['client_name'];
            }
            if (array_key_exists('client_address', $data)) {
                $projectUpdates['client_address'] = $data['client_address'];
            }
            if (! empty($projectUpdates)) {
                $targetProject->update($projectUpdates);
            }
        }

        $sheet->update(array_filter([
            'project_id'   => $sheet->project_id,
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

