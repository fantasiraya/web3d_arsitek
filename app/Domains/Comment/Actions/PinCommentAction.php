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
        return \Illuminate\Support\Facades\Cache::lock("project:{$project->id}:pin_comment", 15)->block(5, function () use ($project, $author, $data) {
            // Verify project revision limit atomically
            $actualRootCommentsCount = Comment::where('project_id', $project->id)
                ->whereNull('parent_id')
                ->count();

            if ($actualRootCommentsCount >= $project->max_revisions_allowed) {
                abort(403, "Proyek ini telah mencapai batas maksimal ({$project->max_revisions_allowed}) revisi. Silakan unpin atau hapus komentar sebelumnya untuk menambahkan komentar baru.");
            }

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

            // Synchronize revision counter on the project with actual root comments in DB
            $project->update(['current_revision_count' => $actualRootCommentsCount + 1]);

            return $comment;
        });
    }
}
