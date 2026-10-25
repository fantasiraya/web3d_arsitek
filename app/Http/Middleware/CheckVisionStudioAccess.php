<?php

namespace App\Http\Middleware;

use App\Domains\Billing\Services\SubscriptionLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVisionStudioAccess
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->limitService->canAccessVisionStudio($user)) {
            $message = 'Fitur AI Vision Studio hanya tersedia untuk pengguna paket Pro dan Enterprise. Silakan upgrade paket Anda untuk menikmati fitur ini.';

            if ($request->header('X-Inertia') || $request->inertia()) {
                return redirect()->back()->with('upgrade_modal', [
                    'feature'       => 'vision',
                    'title'         => 'Fitur AI Vision Studio',
                    'description'   => $message,
                    'required_plan' => 'Pro',
                ])->with('warning', $message);
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $message,
                ], 403);
            }

            return redirect()->route('plans.index')->with('warning', $message);
        }

        return $next($request);
    }
}

