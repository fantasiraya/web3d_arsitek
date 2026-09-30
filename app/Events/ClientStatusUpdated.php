<?php

namespace App\Events;

use App\Domains\Project\Models\ProjectClient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClientStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ProjectClient $client,
        public readonly string $architectId,
    ) {}

    /**
     * Broadcast ke private channel milik arsitek pemilik project.
     * Channel ini sudah ada otomatis via Reverb (App.Models.User.{id}).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.' . $this->architectId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'client.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'project_id'  => $this->client->project_id,
            'client_id'   => $this->client->id,
            'email'       => $this->client->email,
            'status'      => $this->client->status,
            'accepted_at' => $this->client->accepted_at?->toISOString(),
        ];
    }
}
