<?php

namespace App\Http\Middleware;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProjectClientAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

        // If route binding wasn't resolved automatically
        if (is_string($project)) {
            $project = Project::findOrFail($project);
        }

        if (! $project) {
            abort(404, 'Project not found.');
        }

        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        // Project Owner (Architect) always has access
        if ($project->user_id === $user->id) {
            return $next($request);
        }

        // Super admin dapat akses semua proyek
        // Bypass Spatie cache — langsung query DB dengan kedua kemungkinan model_type
        $isSuperAdmin = \DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.model_type', [
                get_class($user),
                \App\Domains\Auth\Models\User::class,
            ])
            ->where('roles.name', 'super_admin')
            ->exists();

        if ($isSuperAdmin) {
            return $next($request);
        }

        // Check if the user is an accepted client for this project
        $clientAccess = ProjectClient::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('status', ProjectClient::STATUS_ACCEPTED)
            ->exists();

        if (! $clientAccess) {
            abort(403, 'You do not have access to this project.');
        }

        return $next($request);
    }
}
