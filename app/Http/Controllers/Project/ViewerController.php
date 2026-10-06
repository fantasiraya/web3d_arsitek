<?php

namespace App\Http\Controllers\Project;

use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function show(Request $request, string $projectId)
    {
        // Access control is already enforced by ProjectClientAccessMiddleware
        // on the route — no need to repeat those checks here.

        // Load project with only the columns the viewer needs.
        // Comments: only root pins (parent_id IS NULL), select specific columns
        // to avoid loading heavy TEXT/DECIMAL fields unnecessarily.
        $project = Project::select([
            'id', 'user_id', 'title', 'slug', 'description',
            'file_path', 'file_size_bytes', 'is_draco_compressed',
            'share_token', 'max_revisions_allowed', 'current_revision_count',
            'created_at', 'updated_at',
        ])
        ->with([
            // Only need latest version for viewer to pick the right file
            'versions' => fn ($q) => $q
                ->select(['id', 'project_id', 'version_number', 'file_path', 'is_draco_compressed'])
                ->orderBy('version_number', 'desc'),

            // Spatial pin comments — load only necessary columns
            'comments' => fn ($q) => $q
                ->select([
                    'id', 'project_id', 'user_id', 'parent_id', 'content',
                    'position_x', 'position_y', 'position_z',
                    'normal_x', 'normal_y', 'normal_z',
                    'status', 'is_pinned', 'created_at', 'updated_at',
                ])
                ->whereNull('parent_id')
                ->with(['user:id,name,email,avatar'])
                ->orderBy('created_at', 'desc'),
        ])
        ->findOrFail($projectId);

        // Sync revision count using a single COUNT query.
        // Only write when stale — avoids unnecessary UPDATE on every view.
        $actualCount = DB::table('comments')
            ->where('project_id', $project->id)
            ->whereNull('parent_id')
            ->count();

        if ($project->current_revision_count !== $actualCount) {
            DB::table('projects')
                ->where('id', $project->id)
                ->update(['current_revision_count' => $actualCount]);

            $project->current_revision_count = $actualCount;
        }

        return Inertia::render('Project/Viewer', [
            'project' => $project,
        ]);
    }
}
