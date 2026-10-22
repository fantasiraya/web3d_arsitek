<?php

namespace App\Http\Controllers\Project;

use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Actions\CreateProjectAction;
use App\Domains\Project\Actions\ExtractSkpThumbnailAction;
use App\Domains\Project\Actions\InviteClientAction;
use App\Domains\Project\Actions\RevokeClientAccessAction;
use App\Domains\Project\Actions\UploadProjectFileAction;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Events\ClientStatusUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        protected CreateProjectAction $createProjectAction,
        protected UploadProjectFileAction $uploadProjectFileAction,
        protected ExtractSkpThumbnailAction $extractSkpThumbnailAction,
        protected InviteClientAction $inviteClientAction,
        protected RevokeClientAccessAction $revokeClientAccessAction,
        protected SubscriptionLimitService $limitService
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
        $user = $request->user();

        // ── 0. Cek jika upload gagal karena batasan PHP server ──
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        if ($contentLength > 0 && empty($request->all()) && empty($request->allFiles())) {
            $postMax = ini_get('post_max_size');
            return back()->withErrors([
                'file' => "Ukuran upload melebihi batas konfigurasi server PHP (post_max_size: {$postMax}). Silakan jalankan server dengan 'composer run serve' atau perbesar batas upload.",
            ])->withInput();
        }

        if ($request->hasFile('file') && ! $request->file('file')->isValid()) {
            $err = $request->file('file')->getError();
            if ($err === UPLOAD_ERR_INI_SIZE) {
                $uploadMax = ini_get('upload_max_filesize');
                return back()->withErrors([
                    'file' => "Ukuran file melebihi batas upload PHP server ({$uploadMax}). Silakan jalankan server dengan 'composer run serve' atau perbesar upload_max_filesize di php.ini.",
                ])->withInput();
            }
        }

        // ── 1. Cek project limit SEBELUM upload file ──────────
        $canCreate = $this->limitService->canCreateProject($user);
        if (! $canCreate['allowed']) {
            return back()->withErrors(['limit' => $canCreate['reason']])->withInput();
        }

        // ── 2. Cek ukuran file per-plan SEBELUM upload ────────
        $file         = $request->file('file');
        $maxMb        = $this->limitService->getMaxFileSizeMb($user);
        $maxKilobytes = $maxMb * 1024;

        $validated = $request->validate([
            'title'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'client_name'           => ['nullable', 'string', 'max:255'],
            'client_address'        => ['nullable', 'string', 'max:1000'],
            'max_revisions_allowed' => ['nullable', 'integer', 'min:1', 'max:20'],
            'file'                  => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['glb', 'gltf', 'skp'])) {
                        $fail('Format file harus berupa .glb atau .skp (SketchUp).');
                    }
                },
                "max:{$maxKilobytes}",
            ],
        ], [
            'file.required' => 'File model 3D (.glb atau .skp) wajib diunggah.',
            'file.max'      => "Ukuran file melebihi batas paket Anda ({$maxMb} MB). Upgrade paket untuk batas lebih besar.",
        ]);

        // ── 3. Upload file ke storage ──────────────────────────
        $ext           = strtolower($file->getClientOriginalExtension() ?: 'glb');
        $filename      = Str::random(40) . '.' . $ext;
        $path          = $file->storeAs('projects/models', $filename, 'public');
        $fileSizeBytes = $file->getSize() ?: 0;

        // ── 4. Buat project record ─────────────────────────────
        $project = $this->createProjectAction->execute($user, [
            'title'                => $validated['title'],
            'description'          => $validated['description'] ?? null,
            'client_name'          => $validated['client_name'] ?? null,
            'client_address'       => $validated['client_address'] ?? null,
            'file_path'            => $path,
            'file_size_bytes'      => $fileSizeBytes,
            'is_draco_compressed'  => false,
            'max_revisions_allowed' => $validated['max_revisions_allowed'] ?? 3,
        ]);

        // ── 5. Jika file SketchUp (.skp), ekstrak thumbnail + konversi ke 3D GLB ──
        if ($ext === 'skp') {
            $this->extractSkpThumbnailAction->execute($project, $user, Storage::disk('public')->path($path));
            app(\App\Domains\Project\Actions\ConvertSkpToGlbAction::class)->execute($path);
        }

        // ── 6. Simpan version record + queue Draco compression (GLB only) ─
        $this->uploadProjectFileAction->execute($project, $user, $file);

        return back()->with('success', 'Proyek 3D berhasil dibuat dan siap ditinjau!');
    }

    /**
     * Update project details or revision limit.
     */
    public function update(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $check = $this->limitService->canEditProject($request->user(), $project);
        if (! $check['allowed']) {
            abort(403, $check['reason'] ?? 'Hanya arsitek pemilik proyek yang dapat mengubah pengaturan proyek.');
        }

        $maxMb        = $this->limitService->getMaxFileSizeMb($request->user());
        $maxKilobytes = $maxMb * 1024;

        $validated = $request->validate([
            'title'                 => ['sometimes', 'string', 'max:255'],
            'description'           => ['nullable', 'string', 'max:1000'],
            'client_name'           => ['nullable', 'string', 'max:255'],
            'client_address'        => ['nullable', 'string', 'max:1000'],
            'max_revisions_allowed' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'file'                  => [
                'nullable',
                'file',
                function ($attribute, $value, $fail) {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (! in_array($ext, ['glb', 'gltf', 'skp'])) {
                        $fail('Format file harus berupa .glb atau .skp (SketchUp).');
                    }
                },
                "max:{$maxKilobytes}",
            ],
        ], [
            'file.max' => "Ukuran file melebihi batas paket Anda ({$maxMb} MB).",
        ]);

        $updateData = [];

        if (isset($validated['title'])) {
            $updateData['title'] = $validated['title'];
        }

        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }

        if (array_key_exists('client_name', $validated)) {
            $updateData['client_name'] = $validated['client_name'];
        }

        if (array_key_exists('client_address', $validated)) {
            $updateData['client_address'] = $validated['client_address'];
        }


        if (isset($validated['max_revisions_allowed'])) {
            $updateData['max_revisions_allowed'] = $validated['max_revisions_allowed'];
        }

        if ($request->hasFile('file')) {
            $file          = $request->file('file');
            $disk          = \Illuminate\Support\Facades\Storage::disk('public');

            // Delete the old project-level file before storing the new one
            $oldPath = $project->file_path;
            if (!empty($oldPath) && $disk->exists($oldPath)) {
                $disk->delete($oldPath);

                // Also delete the draco-compressed variant if it exists alongside the original
                if (!str_ends_with($oldPath, '_draco.glb')) {
                    $oldDraco = preg_replace('/\.gl[bt]f?$/i', '_draco.glb', $oldPath);
                    if ($oldDraco && $disk->exists($oldDraco)) {
                        $disk->delete($oldDraco);
                    }
                }

                // If old file was .skp, delete its converted .glb
                if (str_ends_with(strtolower($oldPath), '.skp')) {
                    $oldConvertedGlb = preg_replace('/\.skp$/i', '.glb', $oldPath);
                    if ($oldConvertedGlb && $disk->exists($oldConvertedGlb)) {
                        $disk->delete($oldConvertedGlb);
                    }
                }
            }

            // Invalidate/delete old captures with code THUMB and SKP-01 because the 3D model geometry has changed
            $oldThumbCaptures = \App\Domains\Project\Models\ProjectCapture::where('project_id', $project->id)
                ->whereIn('code', ['THUMB', 'SKP-01'])
                ->get();
            foreach ($oldThumbCaptures as $oldCap) {
                if (!empty($oldCap->file_path) && $disk->exists($oldCap->file_path)) {
                    $disk->delete($oldCap->file_path);
                }
                $oldCap->delete();
            }

            $ext           = strtolower($file->getClientOriginalExtension() ?: 'glb');
            $filename      = Str::random(40) . '.' . $ext;
            $path          = $file->storeAs('projects/models', $filename, 'public');
            $fileSizeBytes = $file->getSize() ?: 0;

            $updateData['file_path']           = $path;
            $updateData['file_size_bytes']      = $fileSizeBytes;
            $updateData['is_draco_compressed']  = false;

            if ($ext === 'skp') {
                $this->extractSkpThumbnailAction->execute($project, $request->user(), $disk->path($path));
                app(\App\Domains\Project\Actions\ConvertSkpToGlbAction::class)->execute($path);
            }

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
     * Update project 3D thumbnail / snapshot.
     */
    public function updateThumbnail(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        if ($project->user_id !== $user->id) {
            abort(403, 'Hanya arsitek pemilik proyek yang dapat memperbarui snapshot.');
        }

        $dataUrl = $request->input('image');
        if (! is_string($dataUrl) || ! preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $type)) {
            return response()->json(['message' => 'Format gambar tidak valid.'], 422);
        }

        $data = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $data = base64_decode($data);
        if (! $data) {
            return response()->json(['message' => 'Gagal membaca data gambar.'], 422);
        }

        $ext = strtolower($type[1]) === 'jpeg' ? 'jpg' : strtolower($type[1]);
        $filename = 'thumb_' . Str::random(16) . '.' . $ext;
        $dir = "projects/{$project->id}/captures";
        $relativePath = "{$dir}/{$filename}";

        Storage::disk('public')->put($relativePath, $data);

        // Delete previous THUMB captures for this project to keep storage clean and latest authoritative
        $oldCaptures = \App\Domains\Project\Models\ProjectCapture::where('project_id', $project->id)
            ->where('code', 'THUMB')
            ->get();
        foreach ($oldCaptures as $old) {
            if (!empty($old->file_path) && Storage::disk('public')->exists($old->file_path)) {
                Storage::disk('public')->delete($old->file_path);
            }
            $old->delete();
        }

        \App\Domains\Project\Models\ProjectCapture::create([
            'id'                    => (string) Str::uuid(),
            'code'                  => 'THUMB',
            'project_id'            => $project->id,
            'user_id'               => $user->id,
            'title'                 => 'Snapshot Model 3D',
            'room_name'             => 'Pratinjau Utama',
            'captured_hour'         => '12:00',
            'file_path'             => $relativePath,
            'file_size_bytes'       => strlen($data),
            'width'                 => 1280,
            'height'                => 720,
            'is_visible_to_clients' => true,
        ]);

        return response()->json([
            'success'       => true,
            'thumbnail_url' => "/storage/{$relativePath}",
        ]);
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

        // Simpan data sebelum dihapus untuk broadcast
        $clientUserId  = $client->user_id;
        $invitationId  = $client->id;
        $projectId     = $project->id;

        $this->revokeClientAccessAction->execute($client);
        $client->delete();

        // Broadcast realtime ke klien agar project langsung hilang dari dashboardnya
        if ($clientUserId !== null) {
            broadcast(new \App\Events\ClientAccessRevoked(
                $projectId,
                $invitationId,
                $clientUserId,
            ));
        }

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

        // Broadcast realtime ke arsitek pemilik project
        broadcast(new ClientStatusUpdated(
            $invitation->fresh(),
            $project->user_id,
        ));

        return redirect()->route('projects.viewer', $project->id)
            ->with('success', 'Undangan diterima! Anda sekarang dapat meninjau dan memberi anotasi pada proyek ini.');
    }

    /**
     * Delete a project.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $check = $this->limitService->canDeleteProject($request->user(), $project);
        if (! $check['allowed']) {
            abort(403, $check['reason'] ?? 'Hanya arsitek pemilik proyek yang dapat menghapus proyek ini.');
        }

        $project->delete();

        return back()->with('success', 'Proyek berhasil dihapus.');
    }
}
