<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);

        try {
            Log::withContext(['request_id' => $requestId]);
        } catch (\Throwable) {
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-Id');

        if (is_string($header) && $this->isTrustedUuid($header)) {
            return strtolower($header);
        }

        return (string) Str::uuid();
    }

    private function isTrustedUuid(string $value): bool
    {
        if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
            return false;
        }

        return Str::isUuid($value);
    }
}
