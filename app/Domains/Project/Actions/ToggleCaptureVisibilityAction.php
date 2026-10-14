<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;

class ToggleCaptureVisibilityAction
{
    public function execute(User $user, Project $project, ProjectCapture $capture, ?bool $isVisible = null): ProjectCapture
    {
        // Only architect (project owner) can toggle client visibility
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat mengatur visibilitas gambar ke klien.');
        }

        if ($capture->project_id !== $project->id) {
            abort(404, 'Gambar tidak ditemukan dalam proyek ini.');
        }

        $capture->is_visible_to_clients = $isVisible ?? ! $capture->is_visible_to_clients;
        $capture->save();

        return $capture;
    }
}

