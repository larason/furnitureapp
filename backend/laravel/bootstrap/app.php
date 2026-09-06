<?php

use App\Http\Middleware\AdministrativeAccess;
use App\Http\Middleware\OperationalAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API-only backend: unauthenticated callers must get a
        // 401 from the `auth` middleware, never an HTML redirect to a `login`
        // route (which does not exist here). Phase 2.7 remaps this into the
        // frozen `errors[]` contract with `AUTHENTICATION_REQUIRED`.
        $middleware->redirectGuestsTo(null);

        // Group D authorization attachment points (roles/policies/permissions).
        // The framework-provided `auth` alias guards every protected group;
        // these aliases mark the Staff/Admin boundary for later enforcement.
        $middleware->alias([
            'operational' => OperationalAccess::class,
            'admin' => AdministrativeAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
