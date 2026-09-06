<?php

use App\Exceptions\Api\ApiExceptionRenderer;
use App\Http\Middleware\AdministrativeAccess;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\OperationalAccess;
use App\Http\Middleware\ValidateJsonBody;
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
        health: null,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(null);

        $middleware->prependToGroup('api', [
            AssignRequestId::class,
        ]);

        $middleware->prependToGroup('web', [
            AssignRequestId::class,
        ]);

        $middleware->appendToGroup('api', [
            ValidateJsonBody::class,
        ]);

        $middleware->alias([
            'operational' => OperationalAccess::class,
            'admin' => AdministrativeAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (Throwable $e, Request $request) => app(ApiExceptionRenderer::class)->render($e, $request));
    })->create();
