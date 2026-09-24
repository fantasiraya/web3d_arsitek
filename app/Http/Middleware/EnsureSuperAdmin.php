<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = $request->user();

        if (! $sessionUser) {
            return redirect('/login');
        }

        // Load fresh user from database with roles (avoid session serialization issues)
        $user = \App\Domains\Auth\Models\User::with('roles')->find($sessionUser->id);

        if (! $user) {
            return redirect('/login');
        }

        // Debug logging
        \Log::info('EnsureSuperAdmin Check', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_class' => get_class($user),
            'guard_name_property' => $user->guard_name ?? 'not set',
            'getDefaultGuardName' => method_exists($user, 'getDefaultGuardName') ? $user->getDefaultGuardName() : 'method not exists',
            'roles' => $user->roles->pluck('name', 'guard_name')->toArray(),
            'hasRole_no_guard' => $user->hasRole('super_admin'),
            'hasRole_with_web' => $user->hasRole('super_admin', 'web'),
        ]);

        // Check if user has super_admin role with explicit web guard
        if (! $user->hasRole('super_admin', 'web')) {
            abort(403, 'Access denied. Super admin privileges required.');
        }

        return $next($request);
    }
}
