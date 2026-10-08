<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRabAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Cek apakah user memiliki akses ke fitur RAB (hanya Pro dan Enterprise)
        if ($user && $user->subscription_status === 'free') {
            if ($request->wantsJson() || $request->inertia()) {
                return response()->json([
                    'message' => 'Fitur RAB & Lembar Kerja hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda untuk mengakses fitur ini.',
                ], 403);
            }

            abort(403, 'Fitur RAB & Lembar Kerja hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda untuk mengakses fitur ini.');
        }

        return $next($request);
    }
}
