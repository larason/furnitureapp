<?php

use App\Exceptions\Api\ApiExceptionRenderer;
use App\Exceptions\Handler;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\AdministrativeAccess;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateClerk;
use App\Http\Middleware\AuthenticateClerkIfPresent;
use App\Http\Middleware\CustomerCartAccess;
use App\Http\Middleware\CustomerSubmissionAccess;
use App\Http\Middleware\EnforceApiRequestLimits;
use App\Http\Middleware\EnsureCheckoutEnabled;
use App\Http\Middleware\EnsureEnquiriesEnabled;
use App\Http\Middleware\EnsureFurnitureRequestsEnabled;
use App\Http\Middleware\OperationalAccess;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\StaffOrAdminAccess;
use App\Http\Middleware\ValidateApiRequestLimits;
use App\Http\Middleware\ValidateGuestCartMutation;
use App\Http\Middleware\ValidateJsonBody;
use App\Support\CorsHeaders;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\ValidatePostSize;
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

        $middleware->prepend([
            AssignRequestId::class,
        ]);
        $middleware->replace(ValidatePostSize::class, ValidateApiRequestLimits::class);

        $middleware->prependToGroup('api', [
            HandleCors::class,
        ]);

        $middleware->appendToGroup('api', [
            ValidateJsonBody::class,
            AddSecurityHeaders::class,
        ]);

        $middleware->alias([
            'clerk.auth' => AuthenticateClerk::class,
            'clerk.optional' => AuthenticateClerkIfPresent::class,
            'customer-cart' => CustomerCartAccess::class,
            'customer-submission' => CustomerSubmissionAccess::class,
            'checkout.enabled' => EnsureCheckoutEnabled::class,
            'requests.enabled' => EnsureFurnitureRequestsEnabled::class,
            'enquiries.enabled' => EnsureEnquiriesEnabled::class,
            'guest-cart-mutation' => ValidateGuestCartMutation::class,
            'operational' => OperationalAccess::class,
            'admin' => AdministrativeAccess::class,
            'permission' => RequirePermission::class,
            'staff-or-admin' => StaffOrAdminAccess::class,
        ]);

        $middleware->priority([
            AssignRequestId::class,
            EnforceApiRequestLimits::class,
            AuthenticateClerk::class,
            AuthenticateClerkIfPresent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(fn (Throwable $e, Request $request) => app(ApiExceptionRenderer::class)->render($e, $request));
        $exceptions->respond(function ($response, Throwable $_, Request $request) {
            if (! $request->is('api/*')) {
                return $response;
            }

            return CorsHeaders::apply(AddSecurityHeaders::apply($response, $request), $request);
        });
    })
    ->withSingletons([
        ExceptionHandler::class => Handler::class,
    ])
    ->create();
