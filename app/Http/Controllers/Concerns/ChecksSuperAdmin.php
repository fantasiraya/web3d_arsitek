<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait untuk cek super_admin via DB langsung.
 * Bypass Spatie Permission cache yang bermasalah karena model class mismatch.
 */
trait ChecksSuperAdmin
{
    protected function isSuperAdmin(Model $user): bool
    {
        return \DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->whereIn('model_has_roles.model_type', [
                get_class($user),
                \App\Domains\Auth\Models\User::class,
            ])
            ->where('roles.name', 'super_admin')
            ->exists();
    }
}
