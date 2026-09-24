<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Models\UserPlanOverride;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\SystemConfig\Services\AuditLogger;

class SetUserProjectLimitOverrideAction
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SubscriptionLimitService $limitService
    ) {}

    public function execute(
        User $targetUser,
        ?int $customLimit,
        bool $isUnlimited = false,
        ?string $reason = null
    ): UserPlanOverride {
        $oldEffective = $this->limitService->getEffectiveProjectLimit($targetUser);
        $oldOverride = $targetUser->planOverride;

        $override = UserPlanOverride::updateOrCreate(
            ['user_id' => $targetUser->id],
            [
                'custom_project_limit' => $isUnlimited ? null : $customLimit,
                'is_unlimited' => $isUnlimited,
                'reason' => $reason,
            ]
        );

        $admin = auth()->user();
        $adminName = $admin?->name ?? 'Admin';
        $oldDisplay = $oldEffective === null ? 'Unlimited' : (string) $oldEffective;
        $newDisplay = $isUnlimited ? 'Unlimited' : (string) $customLimit;
        $summary = "Admin {$adminName} changed {$targetUser->name} project limit from {$oldDisplay} to {$newDisplay}.";

        $this->auditLogger->log(
            action: 'user.project_limit.overridden',
            targetUser: $targetUser,
            oldValue: [
                'effective_limit' => $oldEffective,
                'custom_project_limit' => $oldOverride?->custom_project_limit,
                'is_unlimited' => $oldOverride?->is_unlimited,
            ],
            newValue: [
                'custom_project_limit' => $isUnlimited ? null : $customLimit,
                'is_unlimited' => $isUnlimited,
                'effective_limit' => $isUnlimited ? null : $customLimit,
            ],
            metadata: [
                'reason' => $reason,
                'summary' => $summary,
            ]
        );

        return $override;
    }
}
