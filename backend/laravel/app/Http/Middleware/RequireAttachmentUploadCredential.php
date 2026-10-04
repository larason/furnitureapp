<?php

namespace App\Http\Middleware;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAttachmentUploadCredential
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null && blank($request->header('X-Upload-Token'))) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'Authentication or an upload capability is required.', 401);
        }

        return $next($request);
    }
}
