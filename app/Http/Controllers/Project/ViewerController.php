<?php

namespace App\Http\Controllers\Project;

use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function show(Request $request, string $projectId)
    {
        $project = Project::with([
            'versions',
            'comments' => fn ($q) => $q->whereNull('parent_id')->with('user')->orderBy('created_at', 'desc'),
        ])->findOrFail($projectId);

        $user = $request->user();

        // ── Cek akses ─────────────────────────────────────────
        $hasAccess = false;

        // 1. Owner project
        if ($project->user_id === $user->id) {
            $hasAccess = true;
        }

        // 2. Super admin — query DB langsung, hardcode model class sesuai DB
        if (!$hasAccess) {
            $hasAccess = \DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->whereIn('model_has_roles.model_type', [
                    get_class($user),
                    \App\Domains\Auth\Models\User::class, // fallback jika user resolve sebagai App\Models\User
                ])
                ->where('roles.name', 'super_admin')
                ->exists();
        }

        // 3. Accepted client
        if (!$hasAccess) {
            $hasAccess = ProjectClient::where('project_id', $project->id)
                ->where('user_id', $user->id)
                ->where('status', ProjectClient::STATUS_ACCEPTED)
                ->exists();
        }

        \Log::debug('[Viewer] user='.$user->email.' class='.get_class($user).' project='.$project->id.' hasAccess='.($hasAccess?'true':'false'));

        if (!$hasAccess) {
            abort(403, 'You do not have access to this project.');
        }

        // ── Sync revision count ───────────────────────────────
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
