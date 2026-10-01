<?php

namespace App\Events;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientInvitationReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ProjectClient $invitation,
        public readonly Project $project,
        public readonly string $clientUserId,
    ) {}

    /**
     * Broadcast ke private channel klien yang diundang.
     * Channel ini sama dengan channel auth default user (sudah ada via Reverb).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->clientUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'invitation.received';
    }

    public function broadcastWith(): array
    {
        $architect = $this->project->user;

        return [
            'invitation_id'            => $this->invitation->id,
            'invitation_status'        => $this->invitation->status,
            'invited_at'               => $this->invitation->invited_at->toISOString(),
            'accepted_at'              => $this->invitation->accepted_at?->toISOString(),
            'id'                       => $this->project->id,
            'title'                    => $this->project->title,
            'description'              => $this->project->description,
            'architect_name'           => $architect?->name ?? 'Arsitek',
            'architect_email'          => $architect?->email ?? '',
            'max_revisions_allowed'    => $this->project->max_revisions_allowed,
            'current_revision_count'   => $this->project->current_revision_count,
            'has_reached_revision_limit' => $this->project->has_reached_revision_limit,
            'created_at'               => $this->project->created_at?->toISOString(),
        ];
    }
}
