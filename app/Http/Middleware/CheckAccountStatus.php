<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'suspended') {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Akun Anda ditangguhkan (Suspended). Silakan hubungi administrator.',
                ], 403);
            }

            abort(403, 'Akun Anda ditangguhkan (Suspended). Silakan hubungi administrator.');
        }

        // Update last_active_at periodically if user is logged in
        if ($user && ($user->last_active_at === null || $user->last_active_at->diffInMinutes(now()) >= 5)) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
