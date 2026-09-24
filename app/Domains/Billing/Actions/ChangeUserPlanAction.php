<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\SystemConfig\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChangeUserPlanAction
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SubscriptionLimitService $limitService
    ) {}

    public function execute(
        User $targetUser,
        string|Plan $newPlan,
        string $billingType = 'monthly',
        ?Carbon $expiresAt = null,
        ?string $reason = null
    ): Subscription {
        $plan = $newPlan instanceof Plan ? $newPlan : Plan::where('slug', $newPlan)->firstOrFail();

        $oldStatus = $targetUser->subscription_status;
        $oldPlan = $this->limitService->getPlanForUser($targetUser);
        $oldPlanSlug = $oldPlan->slug ?? $oldStatus;

        return DB::transaction(function () use ($targetUser, $plan, $billingType, $expiresAt, $reason, $oldStatus, $oldPlanSlug) {
            // Expire previous active subscriptions
            Subscription::where('user_id', $targetUser->id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->update(['status' => Subscription::STATUS_EXPIRED]);

            // Create new active subscription
            $subscription = Subscription::create([
                'user_id' => $targetUser->id,
                'plan_id' => $plan->id,
                'plan' => $plan->slug,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_type' => $billingType,
                'auto_renewal' => true,
                'started_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            // Dual-write to users.subscription_status
            $targetUser->update([
                'subscription_status' => $plan->slug,
            ]);

            $admin = auth()->user();
            $adminName = $admin?->name ?? 'Admin';
            $summary = "Admin {$adminName} changed {$targetUser->name} subscription from ".strtoupper($oldPlanSlug).' to '.strtoupper($plan->slug).'.';

            $this->auditLogger->log(
                action: 'user.subscription.changed',
                targetUser: $targetUser,
                oldValue: [
                    'subscription_status' => $oldStatus,
                    'plan' => $oldPlanSlug,
                ],
                newValue: [
                    'subscription_status' => $plan->slug,
                    'plan' => $plan->slug,
                    'plan_id' => $plan->id,
                ],
                metadata: [
                    'reason' => $reason,
                    'summary' => $summary,
                ]
            );

            return $subscription;
        });
    }
}
