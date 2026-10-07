<?php

namespace App\Domains\Rab\Controllers;

use App\Domains\Project\Models\Project;
use App\Domains\Rab\Actions\ApplyGlbQuantitiesAction;
use App\Domains\Rab\Actions\CreateRabDocumentAction;
use App\Domains\Rab\Actions\ExportRabAction;
use App\Domains\Rab\Actions\FinalizeRabDocumentAction;
use App\Domains\Rab\Actions\ImportQuantityTakeoffAction;
use App\Domains\Rab\Actions\ToggleRabClientVisibilityAction;
use App\Domains\Rab\Actions\UpdateRabDocumentAction;
use App\Domains\Rab\Models\RabDocument;
use App\Domains\Rab\Requests\ApplyGlbQuantitiesRequest;
use App\Domains\Rab\Requests\ImportTakeoffRequest;
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
        protected RabAccessService               $access,
        protected CreateRabDocumentAction        $create,
        protected UpdateRabDocumentAction        $update,
        protected FinalizeRabDocumentAction      $finalize,
        protected ToggleRabClientVisibilityAction $toggleVisibility,
        protected ImportQuantityTakeoffAction    $importer,
        protected ApplyGlbQuantitiesAction       $glbAction,
        protected ExportRabAction                $exporter,
    ) {}

    /**
     * Daftar dokumen RAB untuk satu project.
     * Pemilik: lihat semua. Klien accepted: hanya yang is_visible_to_clients = true.
     */
    public function index(Request $request, Project $project): Response
    {
        $user    = $request->user();
        $isOwner = $project->user_id === $user->id;

        abort_unless($this->access->canAccessProject($user, $project), 403);

        $query = RabDocument::where('project_id', $project->id)
            ->withCount('items');

        if (! $isOwner) {
            $query->where('is_visible_to_clients', true);
        }

        $documents = $query->latest()->get();

        // Templates milik user — untuk dropdown saat buat dokumen baru
        // Hanya dikirim ke owner yang punya akses RAB
        $templates = [];
        if ($isOwner && $this->access->hasPlanAccess($user)) {
            $templates = \App\Domains\Rab\Models\RabTemplate::where('user_id', $user->id)
                ->withCount('items')
                ->orderBy('name')
                ->get(['id', 'name', 'description'])
                ->toArray();
        }

        return Inertia::render('Rab/Index', [
            'project'      => $project->only('id', 'title', 'slug'),
            'documents'    => $documents,
            'templates'    => $templates,
            'can_edit'     => $isOwner && $this->access->hasPlanAccess($user),
            'is_owner'     => $isOwner,
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

    // ── Import Quantity Take-off (Fase B) ─────────────────────────────────────

    /**
     * STEP 1 — Upload file, parse header, kembalikan preview + auto-suggest kolom.
     * Response: JSON (dipakai oleh Import.vue via axios/fetch).
     */
    public function previewImport(Request $request, Project $project, RabDocument $rab): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $request->validate([
            'file'       => ['required', 'file', 'max:10240', 'mimes:csv,xlsx,xls'],
            'header_row' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $preview = $this->importer->preview(
            $user,
            $request->file('file'),
            (int) $request->input('header_row', 1),
        );

        return response()->json($preview);
    }

    /**
     * STEP 2 — Dry-run: cocokkan rows ke price items, kembalikan mapped + unmapped.
     * Response: JSON.
     */
    public function dryRunImport(Request $request, Project $project, RabDocument $rab): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $request->validate([
            'file'         => ['required', 'file', 'max:10240', 'mimes:csv,xlsx,xls'],
            'name_col'     => ['required', 'string'],
            'qty_col'      => ['required', 'string'],
            'unit_col'     => ['nullable', 'string'],
            'material_col' => ['nullable', 'string'],
            'section_col'  => ['nullable', 'string'],
            'header_row'   => ['sometimes', 'integer', 'min:1'],
        ]);

        $result = $this->importer->dryRun(
            $user,
            $request->file('file'),
            $request->input('name_col'),
            $request->input('qty_col'),
            $request->input('unit_col', ''),
            $request->input('material_col', ''),
            $request->input('section_col', ''),
            (int) $request->input('header_row', 1),
        );

        return response()->json($result);
    }

    /**
     * STEP 3 — Eksekusi import: simpan items ke dokumen RAB.
     * Redirect kembali ke halaman show RAB.
     */
    public function import(ImportTakeoffRequest $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        // Susun manual_prices menjadi array berindeks row_index
        $manualPrices = [];
        foreach ($request->input('manual_prices', []) as $mp) {
            $manualPrices[(int) $mp['row_index']] = [
                'unit_price'    => (float) $mp['unit_price'],
                'price_item_id' => $mp['price_item_id'] ?? null,
            ];
        }

        $this->importer->execute(
            $user,
            $rab,
            $request->file('file'),
            $request->input('name_col'),
            $request->input('qty_col'),
            $request->input('unit_col', ''),
            $request->input('material_col', ''),
            $request->input('section_col', ''),
            (int) $request->input('header_row', 1),
            $manualPrices,
        );

        return to_route('rab.show', [$project->id, $rab->id])
            ->with('success', 'Import quantity take-off berhasil. Periksa item yang belum terpetakan.');
    }

    // ── Estimasi dari .glb (Fase C) ───────────────────────────────────────────

    /**
     * Halaman estimator .glb — render Inertia page GlbEstimator.
     * Controller hanya kirim project + dokumen + price items + versi .glb.
     * Kalkulasi dilakukan sepenuhnya di frontend (Three.js).
     */
    public function glbEstimatorPage(Request $request, Project $project, RabDocument $rab): \Inertia\Response
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);
        abort_if($rab->isFinal(), 403, 'Dokumen sudah final. Reopen terlebih dahulu.');

        // Versi .glb terbaru project
        $latestVersion = $project->versions()->latest('version_number')->first();

        // Harga satuan milik user — untuk mapping di frontend
        $priceItems = \App\Domains\Rab\Models\RabPriceItem::where('user_id', $user->id)
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'unit', 'unit_price', 'category']);

        // Mapping rules yang sudah ada — untuk auto-suggest
        $mappings = \App\Domains\Rab\Models\RabMapping::where('user_id', $user->id)
            ->get(['id', 'match_type', 'pattern', 'rab_price_item_id', 'quantity_basis']);

        return \Inertia\Inertia::render('Rab/GlbEstimator', [
            'project'       => $project->only('id', 'title', 'slug'),
            'document'      => $rab->only('id', 'title', 'status', 'source'),
            'latestVersion' => $latestVersion ? [
                'id'        => $latestVersion->id,
                'file_path' => $latestVersion->file_path,
                'version_number' => $latestVersion->version_number,
            ] : null,
            'glbUrl'     => $latestVersion
                ? '/storage/' . $latestVersion->file_path
                : null,
            'priceItems' => $priceItems,
            'mappings'   => $mappings,
        ]);
    }

    /**
     * Terima hasil estimasi dari frontend, buat items RAB is_estimate=true.
     */
    public function fromGlb(ApplyGlbQuantitiesRequest $request, Project $project, RabDocument $rab): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $this->access->authorizeEdit($user, $project);

        $this->glbAction->execute(
            $user,
            $rab,
            $request->validated()['items'],
            $request->input('project_version_id'),
        );

        return to_route('rab.show', [$project->id, $rab->id])
            ->with('success', 'Estimasi dari model 3D berhasil diterapkan. Semua item ditandai "Estimasi" — bukan RAB final kontrak.');
    }

    // ── Export ke Excel ───────────────────────────────────────────────────────

    /**
     * Download dokumen RAB sebagai file Excel (.xlsx).
     * Pemilik: bisa export kapan saja (draft maupun final).
     * Klien accepted: bisa export jika is_visible_to_clients = true.
     */
    public function export(Request $request, Project $project, RabDocument $rab): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $this->access->authorizeView($user, $project, $rab);

        return $this->exporter->execute($rab);
    }
}
