<?php

namespace App\Http\Middleware;

use App\Support\CacheKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Cek apakah user adalah super_admin.
     * Di-cache 5 menit dengan tags user:{id} dan user:{id}:roles.
     * Invalidasi via CacheKeys::flushUserRoles($id) saat role berubah.
     */
    protected function resolveIsAdmin(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return Cache::tags([
            CacheKeys::tagUser($user->id),
            CacheKeys::tagUserRoles($user->id),
        ])->remember(
            CacheKeys::userIsAdmin($user->id),
            CacheKeys::TTL_SHORT,
            fn () => \DB::table('model_has_roles')
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
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $isAdmin = $this->resolveIsAdmin($request);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id'                  => $request->user()->id,
                    'name'                => $request->user()->name,
                    'email'               => $request->user()->email,
                    'avatar'              => $request->user()->avatar
                        ? (str_starts_with($request->user()->avatar, 'http')
                            ? $request->user()->avatar
                            : \Illuminate\Support\Facades\Storage::disk('public')->url($request->user()->avatar))
                        : null,
                    'subscription_status' => $request->user()->subscription_status ?? 'free',
                    'is_pro'              => method_exists($request->user(), 'isPro') ? $request->user()->isPro() : false,
                    'is_admin'            => $isAdmin,
                ] : null,
                'can_access_admin' => $isAdmin,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
