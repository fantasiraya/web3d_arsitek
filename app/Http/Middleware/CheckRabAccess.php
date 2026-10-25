<?php

namespace App\Http\Middleware;

use App\Domains\Project\Models\Project;
use App\Domains\Project\Models\ProjectClient;
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

        if (! $user) {
            return redirect()->route('login');
        }

        // Super admin selalu diizinkan
        if (! empty($user->is_admin)) {
            return $next($request);
        }

        // Cek jika rute berkaitan dengan project dan user adalah klien accepted (bukan owner)
        // Klien yang diundang berhak melihat RAB read-only yang dibagikan (is_visible_to_clients = true)
        $projectParam = $request->route('project');
        if ($projectParam && $request->isMethodSafe()) {
            $project = $projectParam instanceof Project
                ? $projectParam
                : Project::find($projectParam);

            if ($project && $project->user_id !== $user->id) {
                $isAcceptedClient = ProjectClient::where('project_id', $project->id)
                    ->where('user_id', $user->id)
                    ->where('status', ProjectClient::STATUS_ACCEPTED)
                    ->exists();

                if ($isAcceptedClient) {
                    return $next($request);
                }
            }
        }

        // Cek apakah user memiliki akses ke fitur RAB (hanya Pro dan Enterprise)
        if ($user->subscription_status === 'free') {
            $message = 'Fitur RAB & Lembar Kerja hanya tersedia untuk pengguna Pro dan Enterprise. Silakan upgrade paket Anda untuk mengakses fitur ini.';

            // Jika request Inertia, jangan return JSON mentah (karena Inertia akan crash menampilkan modal error JSON)
            if ($request->header('X-Inertia') || $request->inertia()) {
                return redirect()->back()->with('upgrade_modal', [
                    'feature'       => 'rab',
                    'title'         => 'Fitur RAB & Lembar Kerja',
                    'description'   => $message,
                    'required_plan' => 'Pro',
                ])->with('warning', $message);
            }

            // Jika request API murni di luar Inertia
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 403);
            }

            return redirect()->route('plans.index')->with('warning', $message);
        }

        return $next($request);
    }
}
