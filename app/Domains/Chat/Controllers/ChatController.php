<?php

namespace App\Domains\Chat\Controllers;

use App\Domains\Chat\Models\ChatMessage;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Events\ChatMessageSent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ChecksSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    use ChecksSuperAdmin;
    /**
     * GET /projects/{project}/chat
     * Ambil histori pesan (50 terbaru, descending → frontend reverse)
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $this->authorizeAccess($request, $project);

        $messages = ChatMessage::with('sender:id,name')
            ->where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($m) => [
                'id'         => $m->id,
                'project_id' => $m->project_id,
                'sender_id'  => $m->sender_id,
                'message'    => $m->message,
                'read_at'    => $m->read_at?->toISOString(),
                'created_at' => $m->created_at?->toISOString(),
                'sender'     => [
                    'id'   => $m->sender->id,
                    'name' => $m->sender->name,
                ],
            ]);

        return response()->json(['data' => $messages]);
    }

    /**
     * POST /projects/{project}/chat
     * Kirim pesan baru + broadcast realtime
     */
    public function store(Request $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $this->authorizeAccess($request, $project, writeAction: true);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $chatMessage = ChatMessage::create([
            'project_id' => $project->id,
            'sender_id'  => $request->user()->id,
            'message'    => $validated['message'],
        ]);

        $chatMessage->load('sender:id,name');

        // Broadcast realtime ke semua yang join channel
        broadcast(new ChatMessageSent($chatMessage));

        return response()->json([
            'data' => [
                'id'         => $chatMessage->id,
                'project_id' => $chatMessage->project_id,
                'sender_id'  => $chatMessage->sender_id,
                'message'    => $chatMessage->message,
                'read_at'    => null,
                'created_at' => $chatMessage->created_at?->toISOString(),
                'sender'     => [
                    'id'   => $chatMessage->sender->id,
                    'name' => $chatMessage->sender->name,
                ],
            ],
        ], 201);
    }

    /**
     * PATCH /projects/{project}/chat/read
     * Tandai semua pesan yang dikirim lawan bicara sebagai dibaca
     */
    public function markRead(Request $request, string $projectId): JsonResponse
    {
        $project = Project::findOrFail($projectId);
        $this->authorizeAccess($request, $project);

        ChatMessage::where('project_id', $project->id)
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Messages marked as read.']);
    }

    /**
     * Validasi akses: hanya pemilik project atau klien accepted.
     * Super admin diizinkan READ (GET) tapi tidak boleh WRITE (POST).
     */
    private function authorizeAccess(Request $request, Project $project, bool $writeAction = false): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Super admin: boleh baca, tidak boleh kirim pesan
        if ($this->isSuperAdmin($user)) {
            if ($writeAction) {
                abort(403, 'Super admin tidak diizinkan mengirim pesan chat.');
            }
            return;
        }

        if ($project->user_id === $user->id) {
            return; // Arsitek pemilik
        }

        $isClient = ProjectClient::where('project_id', $project->id)
            ->where('user_id', $user->id)
            ->where('status', ProjectClient::STATUS_ACCEPTED)
            ->exists();

        if (! $isClient) {
            abort(403, 'Akses chat tidak diizinkan untuk project ini.');
        }
    }
}
