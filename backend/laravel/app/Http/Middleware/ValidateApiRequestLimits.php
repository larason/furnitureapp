<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateApiRequestLimits
{
    public function __construct(
        private readonly ValidatePostSize $postSize,
        private readonly EnforceApiRequestLimits $apiLimits,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $this->apiLimits->handle(
            $request,
            fn (Request $limitedRequest): Response => $this->postSize->handle($limitedRequest, $next),
        );
    }
}
