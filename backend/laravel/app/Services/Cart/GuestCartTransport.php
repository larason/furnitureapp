<?php

namespace App\Services\Cart;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Reads and issues the guest-cart bearer credential across exactly one
 * transport per interaction: HttpOnly cookie (browser) or X-Guest-Cart-Id
 * header (non-browser). The raw credential never appears in the body/query.
 */
final class GuestCartTransport
{
    public const COOKIE = 'guest_cart_id';

    public const HEADER = 'X-Guest-Cart-Id';

    public function readCredential(Request $request): ?string
    {
        $header = $request->header(self::HEADER);
        $cookie = $request->cookie(self::COOKIE);

        if ($header !== null && $cookie !== null) {
            throw new ApiException(ApiErrorCode::INVALID_VALUE, 'Supply the guest cart credential through exactly one transport.', 422, self::HEADER);
        }

        $credential = $header ?? $cookie;

        if ($credential === null) {
            return null;
        }

        if (! is_string($credential) || ! $this->isValidCredential($credential)) {
            throw new ApiException(ApiErrorCode::AUTHENTICATION_REQUIRED, 'The guest cart credential is invalid.', 401);
        }

        return $credential;
    }

    public function isBrowser(Request $request): bool
    {
        return $request->hasHeader('Origin') || $request->hasCookie(self::COOKIE);
    }

    public function issue(Request $request, JsonResponse $response, string $rawToken): JsonResponse
    {
        if ($this->isBrowser($request)) {
            $response->headers->setCookie(new Cookie(
                name: self::COOKIE,
                value: $rawToken,
                expire: 0,
                path: '/',
                secure: (bool) config('cart.guest_cookie_secure'),
                httpOnly: true,
                raw: false,
                sameSite: 'none',
            ));

            return $response;
        }

        $response->headers->set(self::HEADER, $rawToken);

        return $response;
    }

    private function isValidCredential(string $credential): bool
    {
        return Str::isUuid($credential) && $credential[14] === '4';
    }
}
