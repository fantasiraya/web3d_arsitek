<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Auth\Actions\ToggleUserStatusAction;
use App\Domains\Auth\Models\User;
use App\Domains\Billing\Actions\ChangeUserPlanAction;
use App\Domains\Billing\Actions\RemoveUserProjectLimitOverrideAction;
use App\Domains\Billing\Actions\SetUserProjectLimitOverrideAction;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $planFilter = $request->query('plan');
        $statusFilter = $request->query('status');

        $query = User::with(['planOverride', 'activeSubscription.planModel'])
            ->withCount('projects');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($planFilter && in_array($planFilter, ['free', 'pro', 'enterprise'])) {
            $query->where('subscription_status', $planFilter);
        }

        if ($statusFilter && in_array($statusFilter, ['active', 'suspended'])) {
            $query->where('status', $statusFilter);
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        // Transform pagination items with computed project limits
        $plans = Plan::all()->keyBy('slug');

        $users->getCollection()->transform(function (User $user) use ($plans) {
            $plan = $plans->get($user->subscription_status) ?? $this->limitService->getPlanForUser($user);
            $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
            $override = $user->planOverride;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'subscription_status' => $user->subscription_status ?? 'free',
                'status' => $user->status ?? 'active',
                'project_count' => $user->projects_count,
                'default_limit' => $plan->project_limit,
                'custom_limit' => $override ? ($override->is_unlimited ? 'unlimited' : $override->custom_project_limit) : null,
                'effective_limit' => $effectiveLimit,
                'is_unlimited' => $effectiveLimit === null,
                'has_override' => $override !== null,
                'created_at' => $user->created_at?->format('d M Y, H:i'),
                'last_active_at' => $user->last_active_at?->diffForHumans() ?? 'Belum aktif',
            ];
        });

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'plans' => Plan::where('status', 'active')->get(),
            'filters' => [
                'search' => $search ?? '',
                'plan' => $planFilter ?? '',
                'status' => $statusFilter ?? '',
            ],
        ]);
    }

    public function show(User $user): Response
    {
        $user->load([
            'planOverride',
            'activeSubscription.planModel',
            'subscriptions' => fn ($q) => $q->latest()->take(10),
            'auditLogs' => fn ($q) => $q->with('admin')->latest()->take(15),
        ]);

        $projects = $user->projects()->withCount('versions')->latest()->get();
        $plan = $this->limitService->getPlanForUser($user);
        $effectiveLimit = $this->limitService->getEffectiveProjectLimit($user);
        $override = $user->planOverride;

        $projectCount = $projects->count();
        $remaining = $effectiveLimit === null ? null : max(0, $effectiveLimit - $projectCount);
        $usagePercentage = $effectiveLimit === null
            ? 0
            : ($effectiveLimit > 0 ? min(100, round(($projectCount / $effectiveLimit) * 100)) : 100);

        return Inertia::render('Admin/Users/Show', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'status' => $user->status ?? 'active',
                'subscription_status' => $user->subscription_status ?? 'free',
                'created_at' => $user->created_at?->format('d M Y, H:i'),
                'last_active_at' => $user->last_active_at?->format('d M Y, H:i') ?? 'Belum aktif',
            ],
            'plan_details' => [
                'current_plan' => $plan,
                'default_limit' => $plan->project_limit,
                'custom_limit' => $override ? ($override->is_unlimited ? 'unlimited' : $override->custom_project_limit) : null,
                'effective_limit' => $effectiveLimit,
                'is_unlimited' => $effectiveLimit === null,
                'has_override' => $override !== null,
                'override_reason' => $override?->reason,
            ],
            'project_usage' => [
                'count' => $projectCount,
                'limit' => $effectiveLimit,
                'remaining' => $remaining,
                'usage_percentage' => $usagePercentage,
                'is_exceeded' => $effectiveLimit !== null && $projectCount > $effectiveLimit,
            ],
            'projects' => $projects->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'file_size_bytes' => $p->file_size_bytes,
                'current_revision_count' => $p->current_revision_count,
                'max_revisions_allowed' => $p->max_revisions_allowed,
                'versions_count' => $p->versions_count,
                'created_at' => $p->created_at?->format('d M Y, H:i'),
                'updated_at' => $p->updated_at?->format('d M Y, H:i'),
            ]),
            'active_subscription' => $user->activeSubscription,
            'subscriptions' => $user->subscriptions,
            'audit_logs' => $user->auditLogs->map(fn ($log) => [
                'id' => $log->id,
                'admin_name' => $log->admin?->name ?? 'System',
                'action' => $log->action,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'metadata' => $log->metadata,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->format('d M Y, H:i'),
            ]),
            'available_plans' => Plan::where('status', 'active')->get(),
        ]);
    }

    public function toggleStatus(Request $request, User $user, ToggleUserStatusAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute($user, $validated['status'], $validated['reason'] ?? null);

        $verb = $validated['status'] === 'suspended' ? 'ditangguhkan' : 'diaktifkan kembali';

        return back()->with('success', "Akun pengguna {$user->name} berhasil {$verb}.");
    }

    public function changePlan(Request $request, User $user, ChangeUserPlanAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'exists:plans,slug'],
            'billing_type' => ['nullable', 'string', 'in:monthly,annual,lifetime,custom'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        $action->execute(
            targetUser: $user,
            newPlan: $plan,
            billingType: $validated['billing_type'] ?? 'monthly',
            reason: $validated['reason'] ?? null
        );

        return back()->with('success', "Subscription pengguna {$user->name} berhasil diubah ke paket {$plan->name}.");
    }

    public function setLimitOverride(Request $request, User $user, SetUserProjectLimitOverrideAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'is_unlimited' => ['required', 'boolean'],
            'custom_limit' => ['nullable', 'required_if:is_unlimited,false', 'integer', 'min:1', 'max:10000'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action->execute(
            targetUser: $user,
            customLimit: $validated['is_unlimited'] ? null : (int) $validated['custom_limit'],
            isUnlimited: (bool) $validated['is_unlimited'],
            reason: $validated['reason'] ?? null
        );

        $limitDisplay = $validated['is_unlimited'] ? 'Unlimited' : $validated['custom_limit'];

        return back()->with('success', "Custom project limit untuk {$user->name} berhasil diatur ke {$limitDisplay}.");
    }

    public function removeLimitOverride(User $user, RemoveUserProjectLimitOverrideAction $action): RedirectResponse
    {
        $action->execute($user);

        return back()->with('success', "Custom project limit untuk {$user->name} telah dihapus. Batas proyek kembali ke default paket.");
    }
}
