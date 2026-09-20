<?php

namespace App\Http\Controllers;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected SystemSettingRepository $settings
    ) {}

    /**
     * Display the authenticated user's dashboard (Architect & Client dual-capacity).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Auto-match invitations: link any pending invitations matching user's email
        ProjectClient::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        // 1. Projects owned by this user (Architect capacity)
        $ownedProjects = Project::where('user_id', $user->id)
            ->with([
                'versions' => fn ($q) => $q->orderBy('version_number', 'desc'),
                'invitedClients',
            ])
            ->withCount(['comments as actual_revisions_count' => fn ($q) => $q->whereNull('parent_id')])
            ->latest()
            ->get()
            ->map(function (Project $project) {
                $actualCount = (int) $project->actual_revisions_count;
                if ($project->current_revision_count !== $actualCount) {
                    $project->update(['current_revision_count' => $actualCount]);
                }

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'slug' => $project->slug,
                    'description' => $project->description,
                    'file_path' => $project->file_path,
                    'file_size_bytes' => $project->file_size_bytes,
                    'is_draco_compressed' => $project->is_draco_compressed,
                    'max_revisions_allowed' => $project->max_revisions_allowed,
                    'current_revision_count' => $actualCount,
                    'has_reached_revision_limit' => $actualCount >= $project->max_revisions_allowed,
                    'created_at' => $project->created_at?->diffForHumans(),
                    'versions_count' => $project->versions->count(),
                    'invited_clients' => $project->invitedClients->map(fn (ProjectClient $client) => [
                        'id' => $client->id,
                        'email' => $client->email,
                        'status' => $client->status,
                        'invited_at' => $client->invited_at?->diffForHumans() ?? $client->created_at?->diffForHumans(),
                        'accepted_at' => $client->accepted_at?->diffForHumans(),
                    ])->values()->all(),
                ];
            });

        // 2. Projects where this user is an invited Client
        $clientProjects = ProjectClient::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhere('email', $user->email);
        })
            ->where('status', '!=', ProjectClient::STATUS_REVOKED)
            ->with([
                'project.user',
                'project.versions',
                'project' => fn ($q) => $q->withCount(['comments as actual_revisions_count' => fn ($c) => $c->whereNull('parent_id')]),
            ])
            ->latest('invited_at')
            ->get()
            ->filter(fn (ProjectClient $pc) => $pc->project !== null)
            ->map(function (ProjectClient $client) {
                $project = $client->project;
                $actualCount = (int) ($project->actual_revisions_count ?? $project->current_revision_count);
                if ($project->current_revision_count !== $actualCount) {
                    $project->update(['current_revision_count' => $actualCount]);
                }

                return [
                    'invitation_id' => $client->id,
                    'invitation_status' => $client->status,
                    'invited_at' => $client->invited_at?->diffForHumans() ?? $client->created_at?->diffForHumans(),
                    'accepted_at' => $client->accepted_at?->diffForHumans(),
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'architect_name' => $project->user?->name ?? 'Arsitek',
                    'architect_email' => $project->user?->email ?? '',
                    'max_revisions_allowed' => $project->max_revisions_allowed,
                    'current_revision_count' => $actualCount,
                    'has_reached_revision_limit' => $actualCount >= $project->max_revisions_allowed,
                    'created_at' => $project->created_at?->diffForHumans(),
                ];
            })
            ->values();

        // 3. User stats & limits
        $subscriptionStatus = $user->subscription_status ?? 'free';
        $maxProjects = (int) $this->settings->get("quota.{$subscriptionStatus}.max_projects", 3);
        $ownedCount = $ownedProjects->count();
        $clientCount = $clientProjects->count();

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'subscription_status' => $subscriptionStatus,
                    'is_pro' => $user->isPro(),
                ],
            ],
            'ownedProjects' => $ownedProjects,
            'clientProjects' => $clientProjects,
            'stats' => [
                'owned_count' => $ownedCount,
                'client_count' => $clientCount,
                'max_projects' => $maxProjects,
                'can_create_project' => $ownedCount < $maxProjects,
                'subscription_status' => $subscriptionStatus,
            ],
        ]);
    }
}
