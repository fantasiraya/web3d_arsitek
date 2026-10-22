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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $hasAccess = $this->accessService->hasPlanAccess($user);

        $selectedProjectId = $request->query('project_id');
        $search = trim((string) ($request->query('search') ?? $request->query('q', '')));
        $type = $request->query('type');

        // Query ringan untuk DrawingSheet: hanya kolom yang dibutuhkan dan relasi yang dioptimasi
        $query = DrawingSheet::query()
            ->select([
                'id',
                'project_id',
                'user_id',
                'title',
                'type',
                'scale',
                'paper_size',
                'orientation',
                'width',
                'height',
                'grid_spacing',
                'unit',
                'image_path',
                'sort_order',
                'created_at',
                'updated_at',
            ])
            ->with([
                'project:id,title,client_name,client_address',
                'elements' => function ($q) {
                    $q->select(['id', 'drawing_sheet_id', 'type', 'data', 'sort_order'])
                      ->orderBy('sort_order');
                },
                'captures' => function ($q) {
                    $q->select([
                        'id',
                        'project_id',
                        'drawing_sheet_id',
                        'room_element_id',
                        'code',
                        'room_name',
                        'captured_hour',
                        'file_path',
                        'ai_rendered_path',
                        'sort_order',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('created_at');
                },
            ])
            ->withCount('captures')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('project.invitedClients', function ($clientQ) use ($user) {
                      $clientQ->where('user_id', $user->id)
                              ->where('status', 'accepted');
                  });
            });

        // Filter Project
        if (!empty($selectedProjectId)) {
            $query->where('project_id', $selectedProjectId);
        }

        // Filter Type (plan, elevation, section)
        if (!empty($type) && in_array($type, ['plan', 'elevation', 'section'], true)) {
            $query->where('type', $type);
        }

        // Filter Pencarian Server-side (judul lembar, judul proyek, atau nama klien)
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('project', function ($pQ) use ($search) {
                      $pQ->where('title', 'like', "%{$search}%")
                         ->orWhere('client_name', 'like', "%{$search}%");
                  });
            });
        }

        $sheets = $query->orderBy('updated_at', 'desc')->paginate(12)->withQueryString();

        // Jika request AJAX / JSON (infinite scroll atau live search), kembalikan payload JSON murni yang super ringan
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'sheets' => $sheets,
            ]);
        }

        // Ambil project yang dimiliki atau diundang untuk opsi dropdown filter & form
        $projects = Project::where('user_id', $user->id)
            ->select(['id', 'title', 'client_name', 'client_address'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Ambil visual 3D dari proyek-proyek yang relevan untuk modal penautan
        $allProjectCaptures = \App\Domains\Project\Models\ProjectCapture::whereIn('project_id', $projects->pluck('id'))
            ->select(['id', 'project_id', 'drawing_sheet_id', 'room_element_id', 'code', 'room_name', 'captured_hour', 'file_path', 'ai_rendered_path', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Drawing/Index', [
            'sheets'             => $sheets,
            'projects'           => $projects,
            'allProjectCaptures' => $allProjectCaptures,
            'selectedProjectId'  => $selectedProjectId,
            'filters'            => [
                'search'     => $search,
                'type'       => $type ?? 'all',
                'project_id' => $selectedProjectId,
            ],
            'hasAccess'          => $hasAccess,
        ]);
    }

    /**
     * Tautkan atau lepas visual 3D (captures) ke lembar kerja 2D.
     */
    public function syncCaptures(Request $request, DrawingSheet $sheet): JsonResponse
    {
        $user = $request->user();
        $this->accessService->authorizeEdit($user, $sheet->project);

        $captureIds = $request->input('capture_ids', []);

        // Lepas tautan captures yang sebelumnya terhubung ke sheet ini tapi tidak dipilih lagi
        \App\Domains\Project\Models\ProjectCapture::where('drawing_sheet_id', $sheet->id)
            ->whereNotIn('id', $captureIds)
            ->update(['drawing_sheet_id' => null, 'room_element_id' => null]);

        // Hubungkan captures yang dipilih ke sheet ini
        if (! empty($captureIds)) {
            \App\Domains\Project\Models\ProjectCapture::whereIn('id', $captureIds)
                ->where('project_id', $sheet->project_id)
                ->update(['drawing_sheet_id' => $sheet->id]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Visual 3D berhasil dihubungkan ke lembar kerja.',
        ]);
    }

    /**
     * Simpan lembar kerja 2D baru.
     */
    public function store(CreateDrawingSheetRequest $request, CreateDrawingSheetAction $action): RedirectResponse
    {
        $user = $request->user();
        $project = Project::findOrFail($request->validated('project_id'));

        $sheet = $action->execute($user, $project, $request->validated());

        if ($request->boolean('open_editor')) {
            return redirect()->route('drawings.editor', $sheet->id)
                ->with('success', "Lembar kerja 2D '{$sheet->title}' berhasil dibuat.");
        }

        return redirect()->route('drawings.index')
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

        $projectCaptures = \App\Domains\Project\Models\ProjectCapture::where('project_id', $project->id)
            ->select(['id', 'code', 'room_name', 'captured_hour', 'file_path', 'ai_rendered_path', 'drawing_sheet_id', 'room_element_id'])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('Drawing/Editor', [
            'sheet'           => $sheet,
            'project'         => $project->only(['id', 'title', 'user_id']),
            'elements'        => $sheet->elements,
            'projectSheets'   => $projectSheets,
            'projectCaptures' => $projectCaptures,
            'canEdit'         => $canEdit,
            'architect'       => [
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
    public function update(UpdateDrawingSheetRequest $request, DrawingSheet $sheet, UpdateDrawingSheetAction $action): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $action->execute($user, $sheet, $request->validated());

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi lembar kerja berhasil diperbarui.',
                'sheet'   => $sheet->fresh(),
            ]);
        }

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
        $frameId = $request->query('frame_id');

        return $action->execute($user, $sheet, $frameId);
    }

    /**
     * Ekspor lembar kerja ke format vektor SVG.
     */
    public function exportSvg(Request $request, DrawingSheet $sheet, ExportSvgAction $action): StreamedResponse
    {
        $user = $request->user();
        $rawSvg = $request->input('svg');
        $frameId = $request->query('frame_id');

        return $action->execute($user, $sheet, $rawSvg, $frameId);
    }

    /**
     * Unggah / ganti gambar lembar kerja 2D (CAD / Denah / Arsitektur).
     */
    public function uploadImage(Request $request, DrawingSheet $sheet): JsonResponse
    {
        $user = $request->user();
        $this->accessService->authorizeEdit($user, $sheet->project);

        $request->validate([
            'image' => ['required', 'file', 'image', 'max:20480'],
        ]);

        $file = $request->file('image');

        if ($sheet->image_path && Storage::disk('public')->exists($sheet->image_path)) {
            Storage::disk('public')->delete($sheet->image_path);
        }

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'sheet_' . Str::uuid() . '.' . $extension;
        $path = $file->storeAs("projects/{$sheet->project_id}/sheets", $filename, 'public');

        $sheet->update([
            'image_path' => $path,
        ]);

        return response()->json([
            'message' => 'Gambar lembar kerja 2D berhasil diperbarui.',
            'sheet'   => $sheet->fresh(),
        ]);
    }

    /**
     * Hapus gambar lembar kerja 2D (kembali ke tampilan kanvas vektor).
     */
    public function deleteImage(Request $request, DrawingSheet $sheet): JsonResponse
    {
        $user = $request->user();
        $this->accessService->authorizeEdit($user, $sheet->project);

        if ($sheet->image_path && Storage::disk('public')->exists($sheet->image_path)) {
            Storage::disk('public')->delete($sheet->image_path);
        }

        $sheet->update([
            'image_path' => null,
        ]);

        return response()->json([
            'message' => 'Gambar lembar kerja 2D berhasil dihapus.',
            'sheet'   => $sheet->fresh(),
        ]);
    }
}

