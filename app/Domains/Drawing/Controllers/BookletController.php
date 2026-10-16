<?php

namespace App\Domains\Drawing\Controllers;

use App\Domains\Drawing\Models\DrawingSheet;
use App\Domains\Drawing\Services\DrawingAccessService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Rab\Models\RabDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookletController extends Controller
{
    public function __construct(
        protected DrawingAccessService $accessService
    ) {}

    public function show(Request $request, string $projectId): Response
    {
        $project = Project::with([
            'user:id,name,email,company_name,company_type,phone,city_name,avatar',
        ])->findOrFail($projectId);

        $user = $request->user();
        $isOwner = $user && $project->user_id === $user->id;

        // Otorisasi: pemilik proyek atau klien yang diundang dan accepted
        if (! $isOwner) {
            $isClient = $project->invitedClients()
                ->where('user_id', $user?->id)
                ->where('status', 'accepted')
                ->exists();

            if (! $isClient) {
                abort(403, 'Anda tidak memiliki akses ke lembar kerja proyek ini.');
            }
        }

        // Ambil lembar kerja proyek (semua jenis lembar kerja)
        $selectedSheetId = $request->query('sheet_id');

        $planSheets = DrawingSheet::where('project_id', $project->id)
            ->select('id', 'title', 'scale', 'unit', 'type')
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get();

        $planSheet = null;
        if ($selectedSheetId) {
            $planSheet = DrawingSheet::where('project_id', $project->id)
                ->where('id', $selectedSheetId)
                ->with([
                    'elements' => function ($q) {
                        $q->orderBy('sort_order');
                    },
                    'captures' => function ($q) {
                        $q->orderBy('sort_order')->orderBy('created_at');
                    },
                ])
                ->first();
        }

        if (! $planSheet) {
            $planSheet = DrawingSheet::where('project_id', $project->id)
                ->where('type', DrawingSheet::TYPE_PLAN)
                ->where('title', 'like', '%DENAH%')
                ->with([
                    'elements' => function ($q) {
                        $q->orderBy('sort_order');
                    },
                    'captures' => function ($q) {
                        $q->orderBy('sort_order')->orderBy('created_at');
                    },
                ])
                ->first() ?? DrawingSheet::where('project_id', $project->id)
                ->with([
                    'elements' => function ($q) {
                        $q->orderBy('sort_order');
                    },
                    'captures' => function ($q) {
                        $q->orderBy('sort_order')->orderBy('created_at');
                    },
                ])
                ->first();
        }

        // Ambil Captures proyek
        $capturesQuery = ProjectCapture::where('project_id', $project->id)
            ->orderBy('sort_order')
            ->orderBy('created_at');

        if (! $isOwner) {
            $capturesQuery->where('is_visible_to_clients', true);
        }

        // Sesuai opsi 2: Hanya ambil visual 3D yang sudah pernah disambungkan ke lembar kerja 2D!
        if ($selectedSheetId) {
            // Jika membuka booklet untuk lembar kerja spesifik:
            // HANYA ambil visual 3D yang secara khusus ditautkan ke lembar kerja ini!
            $capturesQuery->where('drawing_sheet_id', $selectedSheetId);
        } else {
            // Jika membuka booklet dari 3D Viewer / Proyek utama:
            // HANYA ambil visual 3D yang SUDAH pernah disambungkan ke lembar kerja 2D!
            // Gambar di galeri 3D yang belum pernah disambungkan ke lembar kerja 2D tidak akan dimunculkan.
            $capturesQuery->whereNotNull('drawing_sheet_id');
        }

        $captures = $capturesQuery->get();

        // Ambil dokumen RAB untuk lampiran opsional
        $rabQuery = RabDocument::where('project_id', $project->id)
            ->with(['items' => function ($q) {
                $q->orderBy('section')->orderBy('sort_order');
            }]);

        if (! $isOwner) {
            $rabQuery->where('is_visible_to_clients', true);
        }

        $rabDocuments = $rabQuery->latest()->get();

        $architect = $project->user;

        return Inertia::render('Drawing/Booklet', [
            'project'         => $project,
            'planSheet'       => $planSheet,
            'planSheets'      => $planSheets,
            'captures'        => $captures,
            'rabDocuments'    => $rabDocuments,
            'isOwner'         => $isOwner,
            'selectedSheetId' => $selectedSheetId,
            'architect'       => [
                'name'         => $architect?->name ?? 'Arsitek',
                'company_name' => $architect?->company_name ?? 'STUDIO ARSITEK',
                'company_type' => $architect?->company_type ?? 'Studio',
                'phone'        => $architect?->phone ?? '',
                'city_name'    => $architect?->city_name ?? '',
                'avatar'       => $architect?->avatar,
            ],
        ]);
    }
}

