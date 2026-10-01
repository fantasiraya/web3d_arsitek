<?php

namespace App\Events;

use App\Domains\Project\Models\ProjectClient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientAccessRevoked implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $projectId,
        public readonly string $invitationId,
        public readonly string $clientUserId,
    ) {}

    /**
     * Broadcast ke private channel klien yang dicabut aksesnya.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->clientUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'access.revoked';
    }

    public function broadcastWith(): array
    {
        return [
            'project_id'    => $this->projectId,
            'invitation_id' => $this->invitationId,
        ];
    }
}
