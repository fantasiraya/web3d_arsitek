<?php

namespace App\Domains\Drawing\Controllers;

use App\Domains\Drawing\Actions\CreateDrawingSheetAction;
use App\Domains\Drawing\Actions\ExportDxfAction;
use App\Domains\Drawing\Actions\ExportSvgAction;
use App\Domains\Drawing\Actions\UpdateDrawingSheetAction;
use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Requests\CreateDrawingSheetRequest;
use App\Domains\Drawing\Requests\UpdateDrawingSheetRequest;
use App\Domains\Drawing\Services\DrawingAccessService;
use App\Domains\Project\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DrawingSheetController extends Controller
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    /**
     * Tampilkan halaman daftar lembar kerja 2D.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $hasAccess = $this->accessService->hasPlanAccess($user);

        // Ambil project yang dimiliki atau diundang
        $projects = Project::where('user_id', $user->id)
            ->select(['id', 'title'])
            ->orderBy('created_at', 'desc')
            ->get();

        $selectedProjectId = $request->query('project_id');

        $query = DrawingSheet::with(['project:id,title'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('project.invitedClients', function ($clientQ) use ($user) {
                      $clientQ->where('user_id', $user->id)
                              ->where('status', 'accepted');
                  });
            });

        if ($selectedProjectId) {
            $query->where('project_id', $selectedProjectId);
        }

        $sheets = $query->orderBy('updated_at', 'desc')->paginate(12)->withQueryString();

        return Inertia::render('Drawing/Index', [
            'sheets'            => $sheets,
            'projects'          => $projects,
            'selectedProjectId' => $selectedProjectId,
            'hasAccess'         => $hasAccess,
        ]);
    }

    /**
     * Simpan lembar kerja 2D baru dan buka di editor.
     */
    public function store(CreateDrawingSheetRequest $request, CreateDrawingSheetAction $action): RedirectResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($request->validated('project_id'));

        $sheet = $action->execute($user, $project, $request->validated());

        return redirect()->route('drawings.editor', $sheet->id)
            ->with('success', "Lembar kerja 2D '{$sheet->title}' berhasil dibuat.");
    }

    /**
     * Buka kanvas editor lembar kerja 2D.
     */
    public function editor(Request $request, DrawingSheet $sheet): Response
    {
        $user = $request->user();
        $project = $sheet->project;

        $this->accessService->authorizeView($user, $project, $sheet);
        $canEdit = $this->accessService->canEdit($user, $project);

        $sheet->load(['elements' => function ($q) {
            $q->orderBy('sort_order');
        }]);

        $projectSheets = DrawingSheet::where('project_id', $project->id)
            ->select(['id', 'title', 'type', 'scale', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $architect = $sheet->user;

        return Inertia::render('Drawing/Editor', [
            'sheet'         => $sheet,
            'project'       => $project->only(['id', 'title', 'user_id']),
            'elements'      => $sheet->elements,
            'projectSheets' => $projectSheets,
            'canEdit'       => $canEdit,
            'architect'     => [
                'name'         => $architect->name,
                'company_name' => $architect->company_name,
                'company_type' => $architect->company_type,
                'phone'        => $architect->phone,
                'city_name'    => $architect->city_name,
            ],
        ]);
    }

    /**
     * Update metadata lembar kerja (nama, tipe, skala, ukuran kertas).
     */
    public function update(UpdateDrawingSheetRequest $request, DrawingSheet $sheet, UpdateDrawingSheetAction $action): RedirectResponse
    {
        $user = $request->user();
        $action->execute($user, $sheet, $request->validated());

        return back()->with('success', 'Konfigurasi lembar kerja berhasil diperbarui.');
    }

    /**
     * Hapus lembar kerja 2D.
     */
    public function destroy(Request $request, DrawingSheet $sheet): RedirectResponse
    {
        $user = $request->user();
        $this->accessService->authorizeEdit($user, $sheet->project);

        $title = $sheet->title;
        $sheet->delete();

        return redirect()->route('drawings.index')
            ->with('success', "Lembar kerja '{$title}' berhasil dihapus.");
    }

    /**
     * Ekspor lembar kerja ke format AutoCAD DXF.
     */
    public function exportDxf(Request $request, DrawingSheet $sheet, ExportDxfAction $action): StreamedResponse
    {
        $user = $request->user();

        return $action->execute($user, $sheet);
    }

    /**
     * Ekspor lembar kerja ke format vektor SVG.
     */
    public function exportSvg(Request $request, DrawingSheet $sheet, ExportSvgAction $action): StreamedResponse
    {
        $user = $request->user();
        $rawSvg = $request->input('svg');

        return $action->execute($user, $sheet, $rawSvg);
    }
}

