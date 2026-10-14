<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;

class UpdateCapturePromptAction
{
    public function execute(User $user, Project $project, ProjectCapture $capture, ?string $prompt): ProjectCapture
    {
        // Hanya arsitek pemilik proyek atau pengambil capture yang dapat mengedit prompt
        if ($project->user_id !== $user->id && $capture->user_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak untuk mengedit prompt gambar ini.');
        }

        $capture->update([
            'prompt' => $prompt,
        ]);

        return $capture;
    }
}

