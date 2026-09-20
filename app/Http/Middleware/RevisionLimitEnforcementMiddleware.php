<?php

namespace App\Http\Middleware;

use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RevisionLimitEnforcementMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if (is_string($project)) {
            $project = Project::findOrFail($project);
        }

        if (! $project) {
            abort(404, 'Project not found.');
        }

        // If the project has comments, ensure current_revision_count is in sync with root comments count
        $rootCommentsCount = Comment::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->count();

        if ($rootCommentsCount > 0 || $project->comments()->exists()) {
            if ($project->current_revision_count !== $rootCommentsCount) {
                $project->update(['current_revision_count' => $rootCommentsCount]);
                $project->refresh();
            }
        }

        if ($project->current_revision_count >= $project->max_revisions_allowed) {
            abort(403, "Proyek ini telah mencapai batas maksimal ({$project->max_revisions_allowed}) revisi. Silakan unpin atau hapus komentar sebelumnya untuk menambahkan komentar baru.");
        }

        return $next($request);
    }
}
