<?php

namespace App\Http\Controllers\Project;

use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Domains\Rab\Models\RabDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function show(Request $request, string $projectId)
    {
        $project = Project::select([
            'id', 'user_id', 'title', 'slug', 'description',
            'file_path', 'file_size_bytes', 'is_draco_compressed',
            'share_token', 'max_revisions_allowed', 'current_revision_count',
            'created_at', 'updated_at',
        ])
        ->with([
            'versions' => fn ($q) => $q
                ->select(['id', 'project_id', 'version_number', 'file_path', 'is_draco_compressed'])
                ->orderBy('version_number', 'desc'),

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

        // Sync revision count — only write when stale
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

        $user    = $request->user();
        $isOwner = $project->user_id === $user?->id;

        // RAB documents visible ke user ini:
        //  - Owner: semua dokumen RAB project ini
        //  - Klien accepted: hanya yang is_visible_to_clients = true
        $rabQuery = RabDocument::where('project_id', $project->id)
            ->select(['id', 'title', 'status', 'is_visible_to_clients', 'total', 'source'])
            ->withCount('items');

        if (! $isOwner) {
            $rabQuery->where('is_visible_to_clients', true);
        }

        $rabDocuments = $rabQuery->latest()->get();

        return Inertia::render('Project/Viewer', [
            'project'      => $project,
            'rabDocuments' => $rabDocuments,
            'isOwner'      => $isOwner,
        ]);
    }
}
