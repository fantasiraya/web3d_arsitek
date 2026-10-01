<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
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
     * Halaman daftar proyek milik arsitek, support server-side search.
     */
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = trim($request->query('search', ''));

        $projectsQuery = Project::where('user_id', $user->id)
            ->with(['invitedClients'])
            ->withCount(['comments as revision_count' => fn ($q) => $q->whereNull('parent_id')]);

        if ($query !== '') {
            $projectsQuery->where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('description', 'LIKE', "%{$query}%");
            });
        }

        $projects = $projectsQuery->latest()->get()->map(function (Project $project) {
            $actual = (int) $project->revision_count;

            return [
                'id'                       => $project->id,
                'title'                    => $project->title,
                'description'              => $project->description,
                'file_size_bytes'          => $project->file_size_bytes,
                'is_draco_compressed'      => $project->is_draco_compressed,
                'max_revisions_allowed'    => $project->max_revisions_allowed,
                'current_revision_count'   => $actual,
                'has_reached_revision_limit' => $actual >= $project->max_revisions_allowed,
                'created_at'               => $project->created_at?->diffForHumans(),
                'invited_clients'          => $project->invitedClients->map(fn (ProjectClient $c) => [
                    'id'          => $c->id,
                    'email'       => $c->email,
                    'status'      => $c->status,
                    'invited_at'  => $c->invited_at?->diffForHumans(),
                    'accepted_at' => $c->accepted_at?->diffForHumans(),
                ])->values()->all(),
            ];
        });

        $canCreate = $this->limitService->canCreateProject($user);

        return Inertia::render('Projects/Index', [
            'projects'   => $projects,
            'search'     => $query,
            'canCreate'  => $canCreate['allowed'],
            'stats'      => [
                'total'        => $projects->count(),
                'max_projects' => $this->limitService->getEffectiveProjectLimit($user) ?? 999,
            ],
        ]);
    }
}
