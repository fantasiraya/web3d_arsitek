<?php

use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\CheckRabAccess;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ProjectClientAccessMiddleware;
use App\Http\Middleware\RevisionLimitEnforcementMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            CheckAccountStatus::class,
        ]);

        $middleware->alias([
            'project.access' => ProjectClientAccessMiddleware::class,
            'project.revision_limit' => RevisionLimitEnforcementMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'account.active' => CheckAccountStatus::class,
            'super_admin' => EnsureSuperAdmin::class,
            'rab.access' => CheckRabAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // ── Render HTTP errors sebagai halaman Inertia Error.vue ──────────────
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Hanya tangani 404, 403, 500 sebagai Inertia page
            if (
                in_array($status, [404, 403, 500, 503])
                && ! $request->is('api/*')
                && ! $request->expectsJson()
                && ! $request->header('X-Inertia')
            ) {
                return \Inertia\Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });

        $exceptions->render(function (PostTooLargeException $e, Request $request) {
            $message = 'Ukuran file terlalu besar! Melebihi batas upload server. Silakan pilih file dengan ukuran lebih kecil.';

            if ($request->header('X-Inertia') || $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'file' => [$message],
                    ],
                ], 422);
            }

            if ($request->hasSession()) {
                return back()->withErrors(['file' => $message]);
            }

            return response($message, 413);
        });
    })->create();
