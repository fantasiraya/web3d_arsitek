<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;

class DeleteCaptureAction
{
    public function execute(User $user, Project $project, ProjectCapture $capture): bool
    {
        // Only architect (project owner) can delete captures
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat menghapus gambar.');
        }

        if ($capture->project_id !== $project->id) {
            abort(404, 'Gambar tidak ditemukan dalam proyek ini.');
        }

        return (bool) $capture->delete();
    }
}

