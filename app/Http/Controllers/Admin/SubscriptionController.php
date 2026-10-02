<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Actions\ChangeUserPlanAction;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Models\Transaction;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\SystemConfig\Services\AuditLogger;
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

    /**
     * List semua transaksi pending (transfer manual yang menunggu konfirmasi)
     * Digabung di halaman yang sama dengan subscriptions untuk kemudahan admin.
     */
    public function pendingTransactions(): \Illuminate\Http\JsonResponse
    {
        $pending = Transaction::with('user:id,name,email')
            ->where('status', Transaction::STATUS_PENDING)
            ->where('payment_type', 'bank_transfer')
            ->latest()
            ->get()
            ->map(fn (Transaction $t) => [
                'id'         => $t->id,
                'order_id'   => $t->order_id,
                'amount'     => $t->amount,
                'user_name'  => $t->user?->name ?? '—',
                'user_email' => $t->user?->email ?? '—',
                'user_id'    => $t->user_id,
                'created_at' => $t->created_at?->diffForHumans(),
                'snap_response' => $t->snap_response,
            ]);

        return response()->json(['data' => $pending]);
    }

    /**
     * Admin konfirmasi transfer manual → set transaction settlement + aktifkan subscription
     */
    public function confirmTransfer(Request $request, Transaction $transaction, ChangeUserPlanAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'plan_slug'    => ['required', 'exists:plans,slug'],
            'billing_type' => ['nullable', 'string', 'in:monthly,annual,lifetime'],
            'notes'        => ['nullable', 'string', 'max:255'],
        ]);

        // 1. Set transaction ke settlement
        $transaction->update([
            'status'  => Transaction::STATUS_SETTLEMENT,
            'paid_at' => now(),
            'snap_response' => array_merge(
                $transaction->snap_response ?? [],
                ['manual_confirmed_by' => auth()->id(), 'notes' => $validated['notes'] ?? null]
            ),
        ]);

        // 2. Aktifkan subscription
        $user = User::findOrFail($transaction->user_id);
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();
        $action->execute(
            targetUser: $user,
            newPlan:    $plan,
            billingType: $validated['billing_type'] ?? 'monthly',
            reason:     "Manual konfirmasi transfer oleh admin. Order: {$transaction->order_id}",
        );

        return back()->with('success', "Transfer {$transaction->order_id} dikonfirmasi. Akun {$user->name} diupgrade ke {$plan->name}.");
    }

    /**
     * Admin aktivasi manual (tanpa transaksi) — langsung upgrade plan user
     */
    public function manualActivate(Request $request, ChangeUserPlanAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'user_id'      => ['required', 'exists:users,id'],
            'plan_slug'    => ['required', 'exists:plans,slug'],
            'billing_type' => ['nullable', 'string', 'in:monthly,annual,lifetime'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        // Buat transaction record sebagai bukti aktivasi manual
        Transaction::create([
            'user_id'      => $user->id,
            'order_id'     => 'MANUAL-' . strtoupper(substr(uniqid(), -8)),
            'amount'       => '0',
            'payment_type' => 'manual',
            'status'       => Transaction::STATUS_SETTLEMENT,
            'paid_at'      => now(),
            'snap_response' => [
                'manual_activation' => true,
                'activated_by' => auth()->id(),
                'notes' => $validated['notes'] ?? 'Aktivasi manual oleh admin',
            ],
        ]);

        $action->execute(
            targetUser: $user,
            newPlan:    $plan,
            billingType: $validated['billing_type'] ?? 'monthly',
            reason:     $validated['notes'] ?? 'Aktivasi manual oleh admin',
        );

        return back()->with('success', "Akun {$user->name} berhasil diaktifkan ke paket {$plan->name}.");
    }
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
            'plans' => Plan::where('status', 'active')->get(['id','name','slug']),
            'filters' => [
                'search' => $search ?? '',
                'plan'   => $planFilter ?? '',
                'status' => $statusFilter ?? '',
            ],
            'pendingTransactions' => Transaction::with('user:id,name,email')
                ->where('status', Transaction::STATUS_PENDING)
                ->where('payment_type', 'bank_transfer')
                ->latest()
                ->get()
                ->map(fn (Transaction $t) => [
                    'id'           => $t->id,
                    'order_id'     => $t->order_id,
                    'amount'       => 'Rp ' . number_format((float)$t->amount, 0, ',', '.'),
                    'user_name'    => $t->user?->name ?? '—',
                    'user_email'   => $t->user?->email ?? '—',
                    'user_id'      => $t->user_id,
                    'created_at'   => $t->created_at?->diffForHumans(),
                    'plan_slug'    => ($t->snap_response ?? [])['plan_slug'] ?? null,
                    'billing_type' => ($t->snap_response ?? [])['billing_type'] ?? 'monthly',
                ]),
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
