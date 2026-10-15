<?php

namespace App\Domains\Drawing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Services\DrawingAccessService;
use App\Domains\Project\Models\Project;

class CreateDrawingSheetAction
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public function execute(User $user, Project $project, array $data): DrawingSheet
    {
        $this->accessService->authorizeEdit($user, $project);

        $nextSortOrder = (int) DrawingSheet::where('project_id', $project->id)->max('sort_order') + 1;

        return DrawingSheet::create([
            'project_id'   => $project->id,
            'user_id'      => $user->id,
            'title'        => $data['title'],
            'type'         => $data['type'] ?? DrawingSheet::TYPE_PLAN,
            'scale'        => $data['scale'] ?? '1:100',
            'paper_size'   => $data['paper_size'] ?? 'A3',
            'orientation'  => $data['orientation'] ?? 'landscape',
            'width'        => isset($data['width']) ? (int) $data['width'] : null,
            'height'       => isset($data['height']) ? (int) $data['height'] : null,
            'grid_spacing' => $data['grid_spacing'] ?? 20,
            'unit'         => $data['unit'] ?? 'm',
            'sort_order'   => $data['sort_order'] ?? $nextSortOrder,
        ]);
    }
}

