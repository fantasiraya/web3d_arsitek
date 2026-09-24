<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Models\User;
use App\Domains\SystemConfig\Services\AuditLogger;

class ToggleUserStatusAction
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function execute(User $targetUser, string $status, ?string $reason = null): User
    {
        $oldStatus = $targetUser->status ?? 'active';

        $targetUser->update([
            'status' => $status,
        ]);

        $admin = auth()->user();
        $adminName = $admin?->name ?? 'Admin';
        $verb = $status === 'suspended' ? 'suspended' : 'activated';
        $summary = "Admin {$adminName} {$verb} user {$targetUser->name}.";

        $this->auditLogger->log(
            action: 'user.status.changed',
            targetUser: $targetUser,
            oldValue: ['status' => $oldStatus],
            newValue: ['status' => $status],
            metadata: [
                'reason' => $reason,
                'summary' => $summary,
            ]
        );

        return $targetUser;
    }
}
