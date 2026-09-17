<?php

namespace App\Domains\Project\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\Project\Notifications\ClientInvitationNotification;
use Illuminate\Support\Facades\Notification;

class InviteClientAction
{
    public function execute(Project $project, User $inviter, string $email): ProjectClient
    {
        // Find existing user if they are already registered
        $existingUser = User::where('email', $email)->first();

        // Create or update the pending invitation
        $client = ProjectClient::updateOrCreate(
            [
                'project_id' => $project->id,
                'email' => $email,
            ],
            [
                'invited_by' => $inviter->id,
                'user_id' => $existingUser?->id,
                'status' => ProjectClient::STATUS_PENDING,
                'invited_at' => now(),
            ]
        );

        // Send notification
        Notification::route('mail', $email)
            ->notify(new ClientInvitationNotification($project, $inviter));

        return $client;
    }
}
