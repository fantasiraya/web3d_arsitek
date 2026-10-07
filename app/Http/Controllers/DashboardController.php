<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Transaction;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\Rab\Models\RabDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    /**
     * Display the authenticated user's dashboard (Architect & Client dual-capacity).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Auto-match invitations: link any pending invitations matching user's email.
        // Scoped to only rows that need updating — avoids full-table update.
        ProjectClient::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        // ── 1. Projects owned by this user (Architect capacity) ──────────────
        // withCount for revision count avoids loading all comments into memory.
        // versions loaded with select() — we only need count + version_number.
        // invitedClients loaded with select() — only columns needed for UI.
        $ownedProjects = Project::where('user_id', $user->id)
            ->select([
                'id', 'title', 'slug', 'description', 'file_path',
                'file_size_bytes', 'is_draco_compressed',
                'max_revisions_allowed', 'current_revision_count', 'created_at',
            ])
            ->with([
                'versions:id,project_id,version_number',
                'invitedClients:id,project_id,email,status,invited_at,accepted_at,created_at',
            ])
            ->withCount(['comments as actual_revisions_count' => fn ($q) => $q->whereNull('parent_id')])
            ->latest()
            ->get();

        // Collect stale project IDs to batch-update in a single query
        $staleOwnedIds = [];
        $ownedProjectsMapped = $ownedProjects->map(function (Project $project) use (&$staleOwnedIds) {
            $actualCount = (int) $project->actual_revisions_count;

            if ($project->current_revision_count !== $actualCount) {
                $staleOwnedIds[$project->id] = $actualCount;
            }

            return [
                'id'                     => $project->id,
                'title'                  => $project->title,
                'slug'                   => $project->slug,
                'description'            => $project->description,
                'file_path'              => $project->file_path,
                'file_size_bytes'        => $project->file_size_bytes,
                'is_draco_compressed'    => $project->is_draco_compressed,
                'max_revisions_allowed'  => $project->max_revisions_allowed,
                'current_revision_count' => $actualCount,
                'has_reached_revision_limit' => $actualCount >= $project->max_revisions_allowed,
                'created_at'             => $project->created_at?->diffForHumans(),
                'versions_count'         => $project->versions->count(),
                'invited_clients'        => $project->invitedClients->map(fn (ProjectClient $client) => [
                    'id'          => $client->id,
                    'email'       => $client->email,
                    'status'      => $client->status,
                    'invited_at'  => $client->invited_at?->diffForHumans() ?? $client->created_at?->diffForHumans(),
                    'accepted_at' => $client->accepted_at?->diffForHumans(),
                ])->values()->all(),
            ];
        });

        // Batch-update stale revision counts in one query per chunk instead of N queries
        if (! empty($staleOwnedIds)) {
            foreach (array_chunk($staleOwnedIds, 100, true) as $chunk) {
                $cases  = '';
                $ids    = [];
                foreach ($chunk as $id => $count) {
                    $cases .= "WHEN '{$id}' THEN {$count} ";
                    $ids[]  = $id;
                }
                DB::table('projects')
                    ->whereIn('id', $ids)
                    ->update(['current_revision_count' => DB::raw("CASE id {$cases}END")]);
            }
        }

        // ── 2. Projects where this user is an invited Client ──────────────────
        $clientProjects = ProjectClient::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('email', $user->email);
        })
            ->where('status', '!=', ProjectClient::STATUS_REVOKED)
            ->with([
                'project:id,title,description,user_id,max_revisions_allowed,current_revision_count,created_at',
                'project.user:id,name,email',
            ])
            ->select([
                'id', 'project_id', 'user_id', 'email',
                'status', 'invited_at', 'accepted_at', 'created_at',
            ])
            ->latest('invited_at')
            ->get()
            ->filter(fn (ProjectClient $pc) => $pc->project !== null);

        // Batch revision count for client projects
        $clientProjectIds = $clientProjects
            ->pluck('project.id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $revisionCounts = [];
        if (! empty($clientProjectIds)) {
            $revisionCounts = DB::table('comments')
                ->selectRaw('project_id, COUNT(*) as cnt')
                ->whereIn('project_id', $clientProjectIds)
                ->whereNull('parent_id')
                ->groupBy('project_id')
                ->pluck('cnt', 'project_id')
                ->toArray();

            // Batch-update stale client project counts
            $staleClientIds = [];
            foreach ($clientProjects as $pc) {
                $project    = $pc->project;
                $actual     = (int) ($revisionCounts[$project->id] ?? 0);
                if ($project->current_revision_count !== $actual) {
                    $staleClientIds[$project->id] = $actual;
                }
            }
            if (! empty($staleClientIds)) {
                foreach (array_chunk($staleClientIds, 100, true) as $chunk) {
                    $cases = '';
                    $ids   = [];
                    foreach ($chunk as $id => $count) {
                        $cases .= "WHEN '{$id}' THEN {$count} ";
                        $ids[]  = $id;
                    }
                    DB::table('projects')
                        ->whereIn('id', $ids)
                        ->update(['current_revision_count' => DB::raw("CASE id {$cases}END")]);
                }
            }
        }

        // Hitung RAB visible per project untuk klien — satu query, bukan N queries
        $rabVisibleCounts = [];
        if (! empty($clientProjectIds)) {
            $rabVisibleCounts = DB::table('rab_documents')
                ->selectRaw('project_id, COUNT(*) as cnt')
                ->whereIn('project_id', $clientProjectIds)
                ->where('is_visible_to_clients', true)
                ->groupBy('project_id')
                ->pluck('cnt', 'project_id')
                ->toArray();
        }

        $clientProjectsMapped = $clientProjects->map(function (ProjectClient $client) use ($revisionCounts, $rabVisibleCounts) {
            $project     = $client->project;
            $actualCount = (int) ($revisionCounts[$project->id] ?? $project->current_revision_count);

            return [
                'invitation_id'          => $client->id,
                'invitation_status'      => $client->status,
                'invited_at'             => $client->invited_at?->diffForHumans() ?? $client->created_at?->diffForHumans(),
                'accepted_at'            => $client->accepted_at?->diffForHumans(),
                'id'                     => $project->id,
                'title'                  => $project->title,
                'description'            => $project->description,
                'architect_name'         => $project->user?->name ?? 'Arsitek',
                'architect_email'        => $project->user?->email ?? '',
                'max_revisions_allowed'  => $project->max_revisions_allowed,
                'current_revision_count' => $actualCount,
                'has_reached_revision_limit' => $actualCount >= $project->max_revisions_allowed,
                'created_at'             => $project->created_at?->diffForHumans(),
                'rab_visible_count'      => (int) ($rabVisibleCounts[$project->id] ?? 0),
            ];
        })->values();

        // ── 3. User stats — single call chain using memoized service ─────────
        // getPlanForUser() is memoized; subsequent calls return cached value.
        $plan          = $this->limitService->getPlanForUser($user);
        $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
        $canCreate     = $this->limitService->canCreateProject($user);
        $hasOverride   = $this->limitService->hasCustomLimitOverride($user);

        $ownedCount  = $ownedProjectsMapped->count();
        $clientCount = $clientProjectsMapped->count();

        $limitWarning = null;
        if ($effectiveLimit !== null && $ownedCount > $effectiveLimit) {
            $limitWarning = [
                'message'       => "Your current plan allows {$effectiveLimit} project(s). You currently have {$ownedCount} projects. Please upgrade your plan or contact the administrator.",
                'current_count' => $ownedCount,
                'allowed_limit' => $effectiveLimit,
            ];
        }

        // ── 4. Transactions — paginated at DB level, not in PHP ───────────────
        $txPage    = max(1, (int) $request->query('tx_page', 1));
        $txPerPage = 8;

        $txPaginator = Transaction::where('user_id', $user->id)
            ->select(['id', 'order_id', 'amount', 'payment_type', 'status', 'snap_response', 'created_at', 'paid_at'])
            ->latest()
            ->paginate($txPerPage, ['*'], 'tx_page', $txPage);

        $txData = $txPaginator->getCollection()->map(function (Transaction $t) {
            $snap = $t->snap_response ?? [];
            return [
                'id'           => $t->id,
                'order_id'     => $t->order_id,
                'plan_name'    => $snap['plan_slug'] ?? '—',
                'billing_type' => $snap['billing_type'] ?? '—',
                'amount'       => 'Rp ' . number_format((float) $t->amount, 0, ',', '.'),
                'payment_type' => $t->payment_type,
                'status'       => $t->status,
                'created_at'   => $t->created_at?->format('d M Y, H:i'),
                'paid_at'      => $t->paid_at?->format('d M Y, H:i'),
            ];
        });

        return Inertia::render('Dashboard', [
            'auth' => [
                'user' => [
                    'id'                  => $user->id,
                    'name'                => $user->name,
                    'email'               => $user->email,
                    'subscription_status' => $user->subscription_status ?? 'free',
                    'is_pro'              => $user->isPro(),
                ],
            ],
            'ownedProjects'  => $ownedProjectsMapped,
            'clientProjects' => $clientProjectsMapped,
            'stats'          => [
                'owned_count'          => $ownedCount,
                'client_count'         => $clientCount,
                'max_projects'         => $effectiveLimit ?? 999999,
                'effective_limit'      => $effectiveLimit,
                'can_create_project'   => $canCreate['allowed'],
                'cannot_create_reason' => $canCreate['reason'],
                'subscription_status'  => $user->subscription_status ?? 'free',
                'plan_name'            => $plan->name,
                'has_custom_override'  => $hasOverride,
                'limit_warning'        => $limitWarning,
            ],
            'transactions' => [
                'data'         => $txData->values(),
                'total'        => $txPaginator->total(),
                'current_page' => $txPaginator->currentPage(),
                'last_page'    => $txPaginator->lastPage(),
                'per_page'     => $txPerPage,
            ],
        ]);
    }
}
