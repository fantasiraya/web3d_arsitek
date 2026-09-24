<?php

namespace App\Domains\Billing\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\Billing\Services\SubscriptionLimitService;
use App\Domains\SystemConfig\Services\AuditLogger;

class RemoveUserProjectLimitOverrideAction
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SubscriptionLimitService $limitService
    ) {}

    public function execute(User $targetUser): bool
    {
        $oldOverride = $targetUser->planOverride;

        if (! $oldOverride) {
            return false;
        }

        $oldEffective = $this->limitService->getEffectiveProjectLimit($targetUser);
        $oldOverride->delete();

        // Refresh model relation
        $targetUser->unsetRelation('planOverride');

        $newEffective = $this->limitService->getEffectiveProjectLimit($targetUser);
        $admin = auth()->user();
        $adminName = $admin?->name ?? 'Admin';
        $summary = "Admin {$adminName} restored default project limit for {$targetUser->name}.";

        $this->auditLogger->log(
            action: 'user.project_limit.override_removed',
            targetUser: $targetUser,
            oldValue: [
                'custom_project_limit' => $oldOverride->custom_project_limit,
                'is_unlimited' => $oldOverride->is_unlimited,
                'effective_limit' => $oldEffective,
            ],
            newValue: [
                'custom_project_limit' => null,
                'is_unlimited' => false,
                'effective_limit' => $newEffective,
            ],
            metadata: [
                'summary' => $summary,
            ]
        );

        return true;
    }
}
