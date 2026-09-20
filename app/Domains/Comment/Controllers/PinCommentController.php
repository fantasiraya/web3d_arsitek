<?php

namespace App\Domains\Comment\Controllers;

use App\Domains\Comment\Actions\PinCommentAction;
use App\Domains\Comment\Models\Comment;
use App\Domains\Comment\Requests\StoreCommentRequest;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PinCommentController extends Controller
{
    public function __construct(protected PinCommentAction $action) {}

    /**
     * Store a new root comment (pin) on a project.
     */
    public function store(StoreCommentRequest $request, string $projectId): JsonResponse|RedirectResponse
    {
        $project = Project::findOrFail($projectId);
        $author = $request->user();
        $comment = $this->action->execute($project, $author, $request->validated());

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'data' => $comment->load('user'),
            ], 201);
        }

        return back()->with('success', 'Pin komentar berhasil dibuat.');
    }

    /**
     * Update an existing comment (pin content).
     */
    public function update(Request $request, string $projectId, string $commentId): JsonResponse|RedirectResponse
    {
        $project = Project::findOrFail($projectId);
        $comment = Comment::where('project_id', $project->id)->findOrFail($commentId);

        $currentUser = $request->user();
        if ($currentUser->id !== $comment->user_id && $currentUser->id !== $project->user_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengedit komentar ini.');
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update([
            'content' => $validated['content'],
        ]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Komentar berhasil diperbarui.',
                'data' => $comment->load('user'),
            ]);
        }

        return back()->with('success', 'Komentar berhasil diperbarui.');
    }
}
