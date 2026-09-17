<?php

namespace App\Domains\Comment\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Comment\Models\Comment;
use App\Domains\Project\Models\Project;

class PinCommentAction
{
    /**
     * Create a root comment (pin) for a project and increment revision counter.
     */
    public function execute(Project $project, User $author, array $data): Comment
    {
        // Ensure this is a root comment (no parent_id)
        $comment = Comment::create([
            'project_id' => $project->id,
            'user_id' => $author->id,
            'parent_id' => null,
            'content' => $data['content'] ?? '',
            'position_x' => $data['position_x'],
            'position_y' => $data['position_y'],
            'position_z' => $data['position_z'],
            'normal_x' => $data['normal_x'] ?? null,
            'normal_y' => $data['normal_y'] ?? null,
            'normal_z' => $data['normal_z'] ?? null,
            'status' => Comment::STATUS_OPEN,
        ]);

        // Increment revision counter on the project atomically
        $project->increment('current_revision_count');

        return $comment;
    }
}
