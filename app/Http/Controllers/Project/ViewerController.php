<?php

namespace App\Http\Controllers\Project;

use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function show(string $projectId)
    {
        $project = Project::with([
            'versions',
            'comments' => fn ($q) => $q->whereNull('parent_id')->with('user')->orderBy('created_at', 'desc'),
        ])->findOrFail($projectId);

        // Synchronize current_revision_count to match actual active root comments in DB
        $actualRootCommentsCount = Comment::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->count();

        if ($project->current_revision_count !== $actualRootCommentsCount) {
            $project->update(['current_revision_count' => $actualRootCommentsCount]);
            $project->refresh();
        }

        return Inertia::render('Project/Viewer', [
            'project' => $project,
        ]);
    }
}
