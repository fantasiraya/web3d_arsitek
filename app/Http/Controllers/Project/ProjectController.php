<?php

namespace App\Http\Controllers\Project;

use App\Domains\Project\Actions\CreateProjectAction;
use App\Domains\Project\Actions\InviteClientAction;
use App\Domains\Project\Actions\RevokeClientAccessAction;
use App\Domains\Project\Actions\UploadProjectFileAction;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        protected CreateProjectAction $createProjectAction,
        protected UploadProjectFileAction $uploadProjectFileAction,
        protected InviteClientAction $inviteClientAction,
        protected RevokeClientAccessAction $revokeClientAccessAction
    ) {}

    /**
     * Return list of projects owned by the user (API / navigation).
     */
    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->select(['id', 'title', 'slug', 'current_revision_count', 'max_revisions_allowed', 'created_at'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $projects,
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_revisions_allowed' => ['nullable', 'integer', 'min:1', 'max:20'],
            'file' => ['required', 'file', 'max:102400'], // max 100MB
        ]);

        $user = $request->user();
        $file = $request->file('file');

        // Store model file
        $path = $file->store('projects/models', 'public');
        $fileSizeBytes = $file->getSize() ?: 0;

        $project = $this->createProjectAction->execute($user, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'file_size_bytes' => $fileSizeBytes,
            'is_draco_compressed' => false,
            'max_revisions_allowed' => $validated['max_revisions_allowed'] ?? 3,
        ]);

        // Upload initial version
        $this->uploadProjectFileAction->execute($project, $user, $file);

        return back()->with('success', 'Proyek 3D berhasil dibuat dan siap ditinjau!');
    }

    /**
     * Update project details or revision limit.
     */
    public function update(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat mengubah pengaturan proyek.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_revisions_allowed' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'file' => ['nullable', 'file', 'max:102400'], // max 100MB
        ]);

        $updateData = [];

        if (isset($validated['title'])) {
            $updateData['title'] = $validated['title'];
        }

        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }

        if (isset($validated['max_revisions_allowed'])) {
            $updateData['max_revisions_allowed'] = $validated['max_revisions_allowed'];
        }

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('projects/models', 'public');
            $fileSizeBytes = $file->getSize() ?: 0;

            $updateData['file_path'] = $path;
            $updateData['file_size_bytes'] = $fileSizeBytes;
            $updateData['is_draco_compressed'] = false;

            // Upload new version record & queue draco compression
            $this->uploadProjectFileAction->execute($project, $request->user(), $file);
        }

        if (! empty($updateData)) {
            $project->update($updateData);
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Proyek berhasil diperbarui.',
                'data' => $project,
            ]);
        }

        return back()->with('success', 'Data proyek berhasil diperbarui.');
    }

    /**
     * Invite a client by email to review the project.
     */
    public function inviteClient(Request $request, Project $project): RedirectResponse
    {
        // Only the project owner can invite clients
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat mengundang klien.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->inviteClientAction->execute($project, $request->user(), $validated['email']);

        return back()->with('success', "Undangan berhasil dikirim ke {$validated['email']}!");
    }

    /**
     * Revoke client access to a project.
     */
    public function revokeClient(Request $request, Project $project, ProjectClient $client): RedirectResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat mencabut akses klien.');
        }

        if ($client->project_id !== $project->id) {
            abort(404, 'Data klien tidak ditemukan pada proyek ini.');
        }

        $this->revokeClientAccessAction->execute($client);
        $client->delete();

        return back()->with('success', 'Akses klien berhasil dicabut.');
    }

    /**
     * Accept an invitation as a Client.
     */
    public function acceptInvitation(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();

        $invitation = ProjectClient::where('project_id', $project->id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->where('status', '!=', ProjectClient::STATUS_REVOKED)
            ->firstOrFail();

        $invitation->update([
            'user_id' => $user->id,
            'status' => ProjectClient::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        return redirect()->route('projects.viewer', $project->id)
            ->with('success', 'Undangan diterima! Anda sekarang dapat meninjau dan memberi anotasi pada proyek ini.');
    }

    /**
     * Delete a project.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        if ($project->user_id !== $request->user()->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat menghapus proyek ini.');
        }

        $project->delete();

        return back()->with('success', 'Proyek berhasil dihapus.');
    }
}
