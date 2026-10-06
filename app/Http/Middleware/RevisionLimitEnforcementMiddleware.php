<?php

namespace App\Http\Middleware;

use App\Domains\Project\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RevisionLimitEnforcementMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Checks the project's revision limit before allowing a new pin comment.
     * Uses current_revision_count (maintained via DB trigger / action layer)
     * rather than running a COUNT(*) query on every request.
     * A single COUNT re-sync only happens when the project model signals it
     * is stale (current_revision_count < 0 — defensive guard).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        if (is_string($project)) {
            // Fetch only the two columns we need — avoids loading all project fields
            $project = Project::select(['id', 'current_revision_count', 'max_revisions_allowed'])
                ->findOrFail($project);
        }

        if (! $project) {
            abort(404, 'Project not found.');
        }

        // Defensive: if counter is somehow negative, re-sync once
        if ($project->current_revision_count < 0) {
            $actual = DB::table('comments')
                ->where('project_id', $project->id)
                ->whereNull('parent_id')
                ->count();

            DB::table('projects')
                ->where('id', $project->id)
                ->update(['current_revision_count' => $actual]);

            $project->current_revision_count = $actual;
        }

        if ($project->current_revision_count >= $project->max_revisions_allowed) {
            abort(403, "Proyek ini telah mencapai batas maksimal ({$project->max_revisions_allowed}) revisi. Silakan unpin atau hapus komentar sebelumnya untuk menambahkan komentar baru.");
        }

        return $next($request);
    }
}
