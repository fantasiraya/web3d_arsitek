<?php

namespace App\Http\Middleware;

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

        if ($project->current_revision_count >= $project->max_revisions_allowed) {
            abort(403, 'This project has reached its maximum allowed revisions.');
        }

        return $next($request);
    }
}
