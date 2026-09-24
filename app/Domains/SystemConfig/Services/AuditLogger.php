<?php

namespace App\Domains\SystemConfig\Services;

use App\Domains\Auth\Models\User;
use App\Domains\SystemConfig\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * Log an administrative action.
     *
     * @param  array<string, mixed>|null  $oldValue
     * @param  array<string, mixed>|null  $newValue
     * @param  array<string, mixed>|null  $metadata
     */
    public function log(
        string $action,
        ?User $targetUser = null,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?array $metadata = null,
        ?User $adminUser = null
    ): AuditLog {
        $admin = $adminUser ?? Auth::user();

        if (! $admin) {
            throw new \RuntimeException('Cannot create audit log without an authenticated admin.');
        }

        return AuditLog::create([
            'admin_id' => $admin->id,
            'target_user_id' => $targetUser?->id,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
