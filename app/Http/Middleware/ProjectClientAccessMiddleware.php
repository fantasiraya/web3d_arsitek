<?php

namespace App\Http\Middleware;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Support\CacheKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ProjectClientAccessMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $project = $request->route('project');

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

        // 1. Project owner — in-memory, no DB/cache hit
        if ($project->user_id === $user->id) {
            return $next($request);
        }

        // 2. Super admin — cached via tags user:{id} + user:{id}:roles
        if ($this->isSuperAdmin($user->id)) {
            return $next($request);
        }

        // 3. Accepted client — hits composite index (project_id, user_id, status)
        $hasAccess = ProjectClient::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('status', ProjectClient::STATUS_ACCEPTED)
            ->exists();

        if (! $hasAccess) {
            abort(403, 'You do not have access to this project.');
        }

        return $next($request);
    }

    /**
     * Cek super_admin dengan cache tags.
     * Key & tag diambil dari CacheKeys — konsisten dengan HandleInertiaRequests
     * dan ChecksSuperAdmin trait sehingga satu DB query melayani ketiganya
     * dalam satu cache window.
     */
    protected function isSuperAdmin(string $userId): bool
    {
        return Cache::tags([
            CacheKeys::tagUser($userId),
            CacheKeys::tagUserRoles($userId),
        ])->remember(
            CacheKeys::userIsAdmin($userId),
            CacheKeys::TTL_SHORT,
            fn () => DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $userId)
                ->whereIn('model_has_roles.model_type', [
                    \App\Domains\Auth\Models\User::class,
                    'App\Models\User',
                ])
                ->where('roles.name', 'super_admin')
                ->exists()
        );
    }
}
