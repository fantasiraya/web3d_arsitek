<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Project\Models\Project;
use App\Domains\Rab\Actions\CreateRabDocumentAction;
use App\Domains\Rab\Actions\FinalizeRabDocumentAction;
use App\Domains\Rab\Actions\ToggleRabClientVisibilityAction;
use App\Domains\Rab\Actions\UpdateRabDocumentAction;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Requests\StoreRabDocumentRequest;
use App\Domains\Rab\Requests\UpdateRabDocumentRequest;
use App\Domains\Rab\Services\RabAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RabDocumentController extends Controller
{
    public function __construct(
        protected RabAccessService             $access,
        protected CreateRabDocumentAction      $create,
        protected UpdateRabDocumentAction      $update,
        protected FinalizeRabDocumentAction    $finalize,
        protected ToggleRabClientVisibilityAction $toggleVisibility,
    ) {}

    /**
     * Daftar dokumen RAB untuk satu project.
     * Pemilik: lihat semua. Klien accepted: hanya yang is_visible_to_clients = true.
     */
    public function index(Request $request, Project $project): Response
    {
        $user = $request->user();
        abort_unless($this->access->canAccessProject($user, $project), 403);

        $isOwner = $project->user_id === $user->id;

        $query = RabDocument::where('project_id', $project->id)
            ->withCount('items');

        if (! $isOwner) {
            $query->where('is_visible_to_clients', true);
        }

        $documents = $query->latest()->get();

        return Inertia::render('Rab/Index', [
            'project'    => $project->only('id', 'title', 'slug'),
            'documents'  => $documents,
            'can_edit'   => $isOwner && $this->access->hasPlanAccess($user),
            'is_owner'   => $isOwner,
            'has_rab_plan' => $this->access->hasPlanAccess($user),
        ]);
    }

    /**
     * Detail dokumen RAB beserta items-nya.
     */
    public function show(Request $request, Project $project, RabDocument $rab): Response
    {
        $user = $request->user();
        $this->access->authorizeView($user, $project, $rab);

        $isOwner = $project->user_id === $user->id;
        $canEdit = $isOwner && $this->access->hasPlanAccess($user) && $rab->isDraft();

        $rab->load(['items', 'template']);

        // Kelompokkan items berdasarkan section untuk kemudahan rendering
        $itemsBySection = $rab->items
            ->groupBy('section')
            ->map(fn ($items) => $items->values());

        return Inertia::render('Rab/Show', [
            'project'          => $project->only('id', 'title', 'slug'),
            'document'         => $rab,
            'items_by_section' => $itemsBySection,
            'can_edit'         => $canEdit,
            'is_owner'         => $isOwner,
        ]);
    }

    /**
     * Buat dokumen RAB baru.
     */
    public function store(StoreRabDocumentRequest $request, Project $project): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $document = $this->create->execute($user, $project, $request->validated());

        return to_route('rab.show', [$project->id, $document->id])
            ->with('success', 'Dokumen RAB berhasil dibuat.');
    }

    /**
     * Update metadata dokumen RAB (judul, overhead, PPN).
     */
    public function update(UpdateRabDocumentRequest $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $this->update->execute($rab, $request->validated());

        return back()->with('success', 'Dokumen RAB berhasil diperbarui.');
    }

    /**
     * Hapus dokumen RAB.
     */
    public function destroy(Request $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $rab->delete();

        return to_route('rab.index', $project->id)
            ->with('success', 'Dokumen RAB berhasil dihapus.');
    }

    /**
     * Finalisasi dokumen RAB (draft → final, terkunci).
     */
    public function finalize(Request $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $this->finalize->finalize($rab);

        return back()->with('success', 'Dokumen RAB telah difinalisasi.');
    }

    /**
     * Reopen dokumen RAB (final → draft).
     */
    public function reopen(Request $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $this->finalize->reopen($rab);

        return back()->with('success', 'Dokumen RAB dibuka kembali untuk diedit.');
    }

    /**
     * Toggle visibilitas RAB ke klien.
     */
    public function toggleVisibility(Request $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        // Hanya pemilik yang bisa toggle — tidak perlu can_use_rab untuk switch ini
        abort_unless($project->user_id === $user->id, 403, 'Hanya pemilik project yang dapat mengatur visibilitas RAB.');

        $visible = $request->boolean('is_visible_to_clients');
        $this->toggleVisibility->execute($rab, $visible);

        $msg = $rab->fresh()->is_visible_to_clients
            ? 'RAB sekarang terlihat oleh klien.'
            : 'RAB disembunyikan dari klien.';

        return back()->with('success', $msg);
    }
}
