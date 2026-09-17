<?php

namespace App\Domains\Comment\Controllers;

use App\Domains\Comment\Actions\PinCommentAction;
use App\Domains\Comment\Models\Comment;
use App\Domains\Comment\Requests\StoreCommentRequest;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PinCommentController extends Controller
{
    public function __construct(protected PinCommentAction $action) {}

    /**
     * Store a new root comment (pin) on a project.
     */
    public function store(StoreCommentRequest $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $author = $request->user();
        $comment = $this->action->execute($project, $author, $request->validated());

        return response()->json([
            'data' => $comment->load('user'),
        ], 201);
    }
}
