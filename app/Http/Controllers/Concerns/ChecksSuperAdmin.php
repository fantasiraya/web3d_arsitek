<?php

namespace App\Http\Controllers\Concerns;

use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait untuk cek super_admin dengan cache tags.
 *
 * Key & tag dikontrol oleh CacheKeys — konsisten dengan HandleInertiaRequests
 * dan ProjectClientAccessMiddleware. Satu DB query melayani semua titik
 * pengecekan dalam satu cache window (TTL_SHORT = 5 menit).
 *
 * Invalidasi:
 *   - CacheKeys::flushUserRoles($userId) → hanya flush data role user ini
 *   - CacheKeys::flushUser($userId)      → flush semua cache milik user ini
 */
trait ChecksSuperAdmin
{
    protected function isSuperAdmin(Model $user): bool
    {
        return Cache::tags([
            CacheKeys::tagUser($user->id),
            CacheKeys::tagUserRoles($user->id),
        ])->remember(
            CacheKeys::userIsAdmin($user->id),
            CacheKeys::TTL_SHORT,
            fn () => DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->whereIn('model_has_roles.model_type', [
                    get_class($user),
                    \App\Domains\Auth\Models\User::class,
                ])
                ->where('roles.name', 'super_admin')
                ->exists()
        );
    }

    /**
     * Flush cache role user ini.
     * Panggil setiap kali role di-assign atau di-revoke via Admin Panel.
     */
    protected function flushSuperAdminCache(string $userId): void
    {
        CacheKeys::flushUserRoles($userId);
    }
}
