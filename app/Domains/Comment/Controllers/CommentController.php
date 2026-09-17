<?php

namespace App\Domains\Comment\Controllers;

use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    /**
     * List comments (pins and replies) for a project.
     */
    public function index(string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);

        // Only root comments (pins) are fetched; replies can be loaded via relationship.
        $comments = Comment::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->with(['replies.user', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($comments);
    }
}
