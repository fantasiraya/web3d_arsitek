<?php

namespace App\Actions\Auth;

use App\Domains\Auth\Models\User;
use App\Domains\Project\Models\ProjectClient;

class MatchInvitedClientEmailAction
{
    public function execute(User $user): void
    {
        $pendingInvitations = ProjectClient::where('email', $user->email)
            ->where('status', ProjectClient::STATUS_PENDING)
            ->get();

        foreach ($pendingInvitations as $invitation) {
            $invitation->update([
                'status' => ProjectClient::STATUS_ACCEPTED,
                'user_id' => $user->id,
                'accepted_at' => now(),
            ]);
        }
    }
}
