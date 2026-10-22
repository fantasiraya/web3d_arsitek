<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Transaction;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectCapture;
use App\Domains\Project\Models\ProjectClient;
use App\Domains\Rab\Models\RabDocument;
use Illuminate\Http\JsonResponse;
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
     * Mendukung live server-side search dan infinite scroll super ringan.
     */
    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();
        $tab  = $request->query('tab', 'architect');
        $search = trim((string) ($request->query('search') ?? $request->query('q', '')));

        // Auto-match invitations: link any pending invitations matching user's email.
        ProjectClient::where('email', $user->email)
            ->whereNull('user_id')
            ->update(['user_id' => $user->id]);

        // ── RESPON AJAX / JSON MURNI (Untuk live search & infinite scroll) ──
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            if ($tab === 'client') {
                $clientProjectsQuery = ProjectClient::where(function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('email', $user->email);
                })
                    ->where('status', '!=', ProjectClient::STATUS_REVOKED)
                    ->whereHas('project', function ($pQ) use ($search) {
                        if ($search !== '') {
                            $pQ->where('title', 'like', "%{$search}%")
                               ->orWhere('description', 'like', "%{$search}%")
                               ->orWhereHas('user', function ($uQ) use ($search) {
                                   $uQ->where('name', 'like', "%{$search}%")
                                      ->orWhere('email', 'like', "%{$search}%");
                               });
                        }
                    })
                    ->with([
                        'project:id,title,description,user_id,max_revisions_allowed,current_revision_count,created_at',
                        'project.user:id,name,email',
                    ])
                    ->select([
                        'id', 'project_id', 'user_id', 'email',
                        'status', 'invited_at', 'accepted_at', 'created_at',
                    ])
                    ->latest('invited_at');

                $paginatedClient = $clientProjectsQuery->paginate(12)->withQueryString();
                $mappedClient    = $this->mapClientProjects($paginatedClient);

                return response()->json([
                    'clientProjects' => $mappedClient,
                ]);
            }

            // Default: tab architect (Proyek Saya)
            $ownedProjectsQuery = Project::where('user_id', $user->id)
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
                ->latest();

            if ($search !== '') {
                $ownedProjectsQuery->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $paginatedOwned = $ownedProjectsQuery->paginate(12)->withQueryString();
            $mappedOwned    = $this->mapOwnedProjects($paginatedOwned);

            return response()->json([
                'ownedProjects' => $mappedOwned,
            ]);
        }

        // ── TOTAL HITUNGAN ASLI (Untuk badge tab counter) ──
        $ownedTotalCount = Project::where('user_id', $user->id)->count();
        $clientTotalCount = ProjectClient::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('email', $user->email);
        })
            ->where('status', '!=', ProjectClient::STATUS_REVOKED)
            ->whereHas('project')
            ->count();

        // ── 1. Projects owned by this user (Architect capacity) — Paginasi 12 item ──
        $ownedProjectsQuery = Project::where('user_id', $user->id)
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
            ->latest();

        if ($search !== '' && $tab === 'architect') {
            $ownedProjectsQuery->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $paginatedOwned      = $ownedProjectsQuery->paginate(12)->withQueryString();
        $ownedProjectsMapped = $this->mapOwnedProjects($paginatedOwned);

        // ── 2. Projects where this user is an invited Client — Paginasi 12 item ──
        $clientProjectsQuery = ProjectClient::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                  ->orWhere('email', $user->email);
        })
            ->where('status', '!=', ProjectClient::STATUS_REVOKED)
            ->whereHas('project', function ($pQ) use ($search, $tab) {
                if ($search !== '' && $tab === 'client') {
                    $pQ->where('title', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%")
                       ->orWhereHas('user', function ($uQ) use ($search) {
                           $uQ->where('name', 'like', "%{$search}%")
                              ->orWhere('email', 'like', "%{$search}%");
                       });
                }
            })
            ->with([
                'project:id,title,description,user_id,max_revisions_allowed,current_revision_count,created_at',
                'project.user:id,name,email',
            ])
            ->select([
                'id', 'project_id', 'user_id', 'email',
                'status', 'invited_at', 'accepted_at', 'created_at',
            ])
            ->latest('invited_at');

        $paginatedClient      = $clientProjectsQuery->paginate(12)->withQueryString();
        $clientProjectsMapped = $this->mapClientProjects($paginatedClient);

        // ── 3. User stats — single call chain using memoized service ─────────
        $plan           = $this->limitService->getPlanForUser($user);
        $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
        $canCreate      = $this->limitService->canCreateProject($user);
        $hasOverride    = $this->limitService->hasCustomLimitOverride($user);

        $limitWarning = null;
        if ($effectiveLimit !== null && $ownedTotalCount > $effectiveLimit) {
            $limitWarning = [
                'message'       => "Paket Anda mengizinkan {$effectiveLimit} proyek. Saat ini Anda memiliki {$ownedTotalCount} proyek.",
                'current_count' => $ownedTotalCount,
                'allowed_limit' => $effectiveLimit,
            ];
        }

        // ── 4. Transactions — paginated at DB level ───────────────
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
            'search'         => $search,
            'tab'            => $tab,
            'stats'          => [
                'owned_count'          => $ownedTotalCount,
                'client_count'         => $clientTotalCount,
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

    /**
     * Map koleksi proyek arsitek yang dipaginasi dan ambil thumbnail hanya untuk halaman aktif.
     */
    protected function mapOwnedProjects($paginatedProjects)
    {
        $projectIds = $paginatedProjects->pluck('id');
        $thumbnails = ProjectCapture::whereIn('project_id', $projectIds)
            ->orderBy('created_at', 'desc')
            ->get(['project_id', 'file_path'])
            ->unique('project_id')
            ->pluck('file_path', 'project_id');

        $paginatedProjects->getCollection()->transform(function (Project $project) use ($thumbnails) {
            $actualCount = (int) ($project->actual_revisions_count ?? $project->current_revision_count);
            $thumb       = $thumbnails[$project->id] ?? null;

            return [
                'id'                         => $project->id,
                'title'                      => $project->title,
                'slug'                       => $project->slug,
                'description'                => $project->description,
                'file_path'                  => $project->file_path,
                'thumbnail_url'              => $thumb ? "/storage/{$thumb}" : null,
                'file_size_bytes'            => $project->file_size_bytes,
                'is_draco_compressed'        => $project->is_draco_compressed,
                'max_revisions_allowed'      => $project->max_revisions_allowed,
                'current_revision_count'     => $actualCount,
                'has_reached_revision_limit' => $actualCount >= $project->max_revisions_allowed,
                'created_at'                 => $project->created_at?->diffForHumans(),
                'versions_count'             => $project->versions ? $project->versions->count() : 0,
                'invited_clients'            => $project->invitedClients ? $project->invitedClients->map(fn (ProjectClient $client) => [
                    'id'          => $client->id,
                    'email'       => $client->email,
                    'status'      => $client->status,
                    'invited_at'  => $client->invited_at?->diffForHumans() ?? $client->created_at?->diffForHumans(),
                    'accepted_at' => $client->accepted_at?->diffForHumans(),
                ])->values()->all() : [],
            ];
        });

        return $paginatedProjects;
    }

    /**
     * Map koleksi proyek klien yang dipaginasi secara efisien.
     */
    protected function mapClientProjects($paginatedClientProjects)
    {
        $clientProjectIds = $paginatedClientProjects
            ->pluck('project.id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $revisionCounts   = [];
        $rabVisibleCounts = [];
        if (! empty($clientProjectIds)) {
            $revisionCounts = DB::table('comments')
                ->selectRaw('project_id, COUNT(*) as cnt')
                ->whereIn('project_id', $clientProjectIds)
                ->whereNull('parent_id')
                ->groupBy('project_id')
                ->pluck('cnt', 'project_id')
                ->toArray();

            $rabVisibleCounts = DB::table('rab_documents')
                ->selectRaw('project_id, COUNT(*) as cnt')
                ->whereIn('project_id', $clientProjectIds)
                ->where('is_visible_to_clients', true)
                ->groupBy('project_id')
                ->pluck('cnt', 'project_id')
                ->toArray();
        }

        $paginatedClientProjects->getCollection()->transform(function (ProjectClient $client) use ($revisionCounts, $rabVisibleCounts) {
            $project = $client->project;
            if (! $project) {
                return null;
            }

            $actualCount = (int) ($revisionCounts[$project->id] ?? $project->current_revision_count);

            return [
                'invitation_id'              => $client->id,
                'invitation_status'          => $client->status,
                'invited_at'                 => $client->invited_at?->diffForHumans() ?? $client->created_at?->diffForHumans(),
                'accepted_at'                => $client->accepted_at?->diffForHumans(),
                'id'                         => $project->id,
                'title'                      => $project->title,
                'description'                => $project->description,
                'architect_name'             => $project->user?->name ?? 'Arsitek',
                'architect_email'            => $project->user?->email ?? '',
                'max_revisions_allowed'      => $project->max_revisions_allowed,
                'current_revision_count'     => $actualCount,
                'has_reached_revision_limit' => $actualCount >= $project->max_revisions_allowed,
                'created_at'                 => $project->created_at?->diffForHumans(),
                'rab_visible_count'          => (int) ($rabVisibleCounts[$project->id] ?? 0),
            ];
        });

        return $paginatedClientProjects;
    }
}
