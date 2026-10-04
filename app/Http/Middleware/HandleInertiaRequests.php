<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Cek super_admin via DB langsung — bypass Spatie cache issue
        $isAdmin = false;
        if ($request->user()) {
            $isAdmin = \DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $request->user()->id)
                ->whereIn('model_has_roles.model_type', [
                    get_class($request->user()),
                    \App\Domains\Auth\Models\User::class,
                ])
                ->where('roles.name', 'super_admin')
                ->exists();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() ? [
                    'id'                  => $request->user()->id,
                    'name'                => $request->user()->name,
                    'email'               => $request->user()->email,
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
