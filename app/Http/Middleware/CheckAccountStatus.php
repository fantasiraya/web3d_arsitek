<?php

namespace App\Http\Middleware;

use App\Support\CacheKeys;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckAccountStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // Suspended check — in-memory dari model yang sudah di-load, no extra query
            if ($user->status === 'suspended') {
                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Akun Anda ditangguhkan (Suspended). Silakan hubungi administrator.',
                    ], 403);
                }

                abort(403, 'Akun Anda ditangguhkan (Suspended). Silakan hubungi administrator.');
            }

            // Throttle last_active_at writes — max 1 DB UPDATE per TTL_SHORT per user.
            // Tag user:{id} memungkinkan flush semua aktivitas user saat user dihapus/suspend.
            $written = Cache::tags([CacheKeys::tagUser($user->id)])
                ->has(CacheKeys::userLastActiveWritten($user->id));

            if (! $written) {
                // Pakai DB::table() bukan Eloquent — skip model events & timestamps overhead
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['last_active_at' => now()]);

                Cache::tags([CacheKeys::tagUser($user->id)])
                    ->put(
                        CacheKeys::userLastActiveWritten($user->id),
                        true,
                        CacheKeys::TTL_SHORT
                    );
            }
        }

        return $next($request);
    }
}
