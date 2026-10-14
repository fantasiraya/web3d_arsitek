<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;

class UpdateProjectSunSettingsAction
{
    public function execute(User $user, Project $project, array $data): Project
    {
        // Only architect (project owner) can update sun and location settings
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat mengatur lokasi dan arah utara.');
        }

        $project->update([
            'latitude'    => (float) $data['latitude'],
            'longitude'   => (float) $data['longitude'],
            'north_angle' => (float) $data['north_angle'],
            'solar_date'  => $data['solar_date'] ?? $project->solar_date ?? now()->toDateString(),
        ]);

        return $project;
    }
}

