<?php

namespace App\Domains\Comment\Controllers;

use App\Domains\Comment\Actions\PinCommentAction;
use App\Domains\Comment\Models\Comment;
use App\Domains\Comment\Requests\StoreCommentRequest;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PinCommentController extends Controller
{
    use ChecksSuperAdmin;
    public function __construct(protected PinCommentAction $action) {}

    /**
     * Store a new root comment (pin) on a project.
     */
    public function store(StoreCommentRequest $request, string $projectId): JsonResponse|RedirectResponse
    {
        $project = Project::findOrFail($projectId);
        $author  = $request->user();

        // Super admin tidak boleh tambah pin
        if ($this->isSuperAdmin($author)) {
            abort(403, 'Super admin tidak diizinkan menambahkan pin komentar.');
        }

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

    /**
     * Toggle resolved status of a comment (open ↔ resolved).
     */
    public function toggleResolved(Request $request, string $projectId, string $commentId): JsonResponse|RedirectResponse
    {
        $project     = Project::findOrFail($projectId);
        $comment     = Comment::where('project_id', $project->id)->findOrFail($commentId);
        $currentUser = $request->user();

        // Super admin tidak boleh tandai selesai
        if ($this->isSuperAdmin($currentUser)) {
            abort(403, 'Super admin tidak diizinkan mengubah status pin.');
        }

        if ($currentUser->id !== $comment->user_id && $currentUser->id !== $project->user_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah status komentar ini.');
        }

        $newStatus = $comment->status === Comment::STATUS_RESOLVED
            ? Comment::STATUS_OPEN
            : Comment::STATUS_RESOLVED;

        $comment->update(['status' => $newStatus]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $newStatus === Comment::STATUS_RESOLVED
                    ? 'Pin ditandai selesai.'
                    : 'Pin dibuka kembali.',
                'data' => ['id' => $comment->id, 'status' => $comment->status],
            ]);
        }

        return back()->with('success', 'Status pin berhasil diperbarui.');
    }

    /**
     * Unpin (delete) a comment from the 3D model and database.
     */
    public function destroy(Request $request, string $projectId, string $commentId): JsonResponse|RedirectResponse
    {
        $project = Project::findOrFail($projectId);
        $comment = Comment::where('project_id', $project->id)->findOrFail($commentId);

        $currentUser = $request->user();
        if ($currentUser->id !== $comment->user_id && $currentUser->id !== $project->user_id) {
            abort(403, 'Anda tidak memiliki hak akses untuk menghapus pin komentar ini.');
        }

        // Delete children replies if any
        $comment->replies()->delete();
        $comment->delete();

        // Synchronize current_revision_count with remaining root comments
        $actualRootCommentsCount = Comment::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->count();
        $project->update(['current_revision_count' => $actualRootCommentsCount]);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Pin komentar berhasil dilepas dan dihapus dari database.',
                'id' => $commentId,
            ]);
        }

        return back()->with('success', 'Pin komentar berhasil dilepas.');
    }
}
