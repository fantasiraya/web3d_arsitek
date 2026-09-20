<?php

namespace App\Http\Controllers\Project;

use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function show(string $projectId)
    {
        $project = Project::with(['versions', 'comments.user'])->findOrFail($projectId);

        return Inertia::render('Project/Viewer', [
            'project' => $project,
        ]);
    }
}
