<?php

namespace App\Events;

use App\Domains\Chat\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ChatMessage $message,
    ) {}

    /**
     * Broadcast ke private channel project.{project_id}.chat
     * ShouldBroadcastNow = tanpa queue, langsung kirim (cocok untuk chat realtime)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('project.' . $this->message->project_id . '.chat'),
        ];
    }

    /**
     * Nama event yang diterima di sisi client (Laravel Echo)
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Data yang dikirim ke frontend — hanya field yang dibutuhkan UI
     */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->message->id,
            'project_id' => $this->message->project_id,
            'sender_id'  => $this->message->sender_id,
            'message'    => $this->message->message,
            'created_at' => $this->message->created_at?->toISOString(),
            'sender'     => [
                'id'   => $this->message->sender->id,
                'name' => $this->message->sender->name,
            ],
        ];
    }
}
