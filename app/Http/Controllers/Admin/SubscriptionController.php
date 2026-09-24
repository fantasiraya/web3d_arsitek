<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Actions\ChangeUserPlanAction;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $planFilter = $request->query('plan');
        $statusFilter = $request->query('status');

        $query = Subscription::with(['user', 'planModel']);

        if ($search) {
            $query->whereHas('user', function ($uq) use ($search) {
                $uq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($planFilter) {
            $query->where('plan', $planFilter);
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $subscriptions = $query->latest('started_at')->paginate(15)->withQueryString();

        $subscriptions->getCollection()->transform(function (Subscription $sub) {
            $user = $sub->user;
            $projectCount = $user ? $user->projects()->count() : 0;
            $effectiveLimit = $user ? $this->limitService->getEffectiveProjectLimit($user) : null;
            $hasOverride = $user ? $this->limitService->hasCustomLimitOverride($user) : false;

            return [
                'id' => $sub->id,
                'user' => [
                    'id' => $user?->id,
                    'name' => $user?->name ?? 'Unknown',
                    'email' => $user?->email ?? '-',
                    'subscription_status' => $user?->subscription_status ?? 'free',
                ],
                'plan' => $sub->plan,
                'plan_name' => $sub->planModel?->name ?? ucfirst($sub->plan),
                'status' => $sub->status,
                'billing_type' => $sub->billing_type ?? 'monthly',
                'auto_renewal' => (bool) $sub->auto_renewal,
                'started_at' => $sub->started_at?->format('d M Y, H:i'),
                'expires_at' => $sub->expires_at?->format('d M Y, H:i') ?? 'Selamanya / Aktif',
                'project_count' => $projectCount,
                'effective_limit' => $effectiveLimit,
                'has_override' => $hasOverride,
                'is_over_limit' => $effectiveLimit !== null && $projectCount > $effectiveLimit,
            ];
        });

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'plans' => Plan::where('status', 'active')->get(),
            'filters' => [
                'search' => $search ?? '',
                'plan' => $planFilter ?? '',
                'status' => $statusFilter ?? '',
            ],
        ]);
    }

    public function changePlan(Request $request, ChangeUserPlanAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'plan_slug' => ['required', 'exists:plans,slug'],
            'billing_type' => ['nullable', 'string', 'in:monthly,annual,lifetime,custom'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        $action->execute(
            targetUser: $user,
            newPlan: $plan,
            billingType: $validated['billing_type'] ?? 'monthly',
            reason: $validated['reason'] ?? null
        );

        return back()->with('success', "Paket pengguna {$user->name} berhasil diperbarui ke {$plan->name}.");
    }
}
