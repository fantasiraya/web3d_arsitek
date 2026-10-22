<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Project\Models\ProjectClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsPageController extends Controller
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    /**
     * GET /projects
     * Halaman daftar proyek arsitek dengan server-side search dan infinite scroll ringan.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user  = $request->user();
        $query = trim((string) $request->query('search', ''));

        // Query ringan: hanya kolom esensial dan relasi spesifik
        $projectsQuery = Project::query()
            ->select([
                'id',
                'user_id',
                'title',
                'slug',
                'description',
                'file_path',
                'file_size_bytes',
                'is_draco_compressed',
                'max_revisions_allowed',
                'created_at',
            ])
            ->where('user_id', $user->id)
            ->with([
                'invitedClients:id,project_id,email,status,invited_at,accepted_at',
            ])
            ->withCount(['comments as revision_count' => fn ($q) => $q->whereNull('parent_id')]);

        if ($query !== '') {
            $projectsQuery->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            });
        }

        // Paginasi 12 item per halaman untuk infinite scroll yang cepat
        $paginatedProjects = $projectsQuery->latest()->paginate(12)->withQueryString();

        // Ambil thumbnail HANYA untuk 12 project di halaman aktif, bukan seluruh database
        $pageProjectIds = $paginatedProjects->pluck('id');
        $thumbnails = ProjectCapture::whereIn('project_id', $pageProjectIds)
            ->orderBy('created_at', 'desc')
            ->get(['project_id', 'file_path'])
            ->unique('project_id')
            ->pluck('file_path', 'project_id');

        $paginatedProjects->getCollection()->transform(function (Project $project) use ($thumbnails) {
            $actual = (int) $project->revision_count;
            $thumb  = $thumbnails[$project->id] ?? null;

            return [
                'id'                         => $project->id,
                'title'                      => $project->title,
                'description'                => $project->description,
                'file_path'                  => $project->file_path,
                'thumbnail_url'              => $thumb ? "/storage/{$thumb}" : null,
                'file_size_bytes'            => $project->file_size_bytes,
                'is_draco_compressed'        => $project->is_draco_compressed,
                'max_revisions_allowed'      => $project->max_revisions_allowed,
                'current_revision_count'     => $actual,
                'has_reached_revision_limit' => $actual >= $project->max_revisions_allowed,
                'created_at'                 => $project->created_at?->diffForHumans(),
                'invited_clients'            => $project->invitedClients->map(fn (ProjectClient $c) => [
                    'id'          => $c->id,
                    'email'       => $c->email,
                    'status'      => $c->status,
                    'invited_at'  => $c->invited_at?->diffForHumans(),
                    'accepted_at' => $c->accepted_at?->diffForHumans(),
                ])->values()->all(),
            ];
        });

        // Jika request AJAX / JSON (infinite scroll atau live search), kirimkan JSON murni super cepat
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'projects' => $paginatedProjects,
            ]);
        }

        $canCreate = $this->limitService->canCreateProject($user);
        $totalUserProjects = Project::where('user_id', $user->id)->count();

        return Inertia::render('Projects/Index', [
            'projects'   => $paginatedProjects,
            'search'     => $query,
            'canCreate'  => $canCreate['allowed'],
            'stats'      => [
                'total'        => $totalUserProjects,
                'max_projects' => $this->limitService->getEffectiveProjectLimit($user) ?? 999,
            ],
        ]);
    }
}
