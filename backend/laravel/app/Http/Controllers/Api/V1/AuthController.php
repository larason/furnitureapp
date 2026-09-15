<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use Illuminate\Http\JsonResponse;

class AuthController extends V1Controller
{
    public function register(): JsonResponse
    {
        throw $this->retired();
    }

    public function login(): JsonResponse
    {
        throw $this->retired();
    }

    public function logout(): JsonResponse
    {
        throw $this->retired();
    }

    public function changePassword(): JsonResponse
    {
        throw $this->retired();
    }

    public function passwordForgot(): JsonResponse
    {
        throw $this->retired();
    }

    public function passwordReset(): JsonResponse
    {
        throw $this->retired();
    }

    public function verifyEmail(): JsonResponse
    {
        throw $this->retired();
    }

    public function resendEmailVerification(): JsonResponse
    {
        throw $this->retired();
    }

    private function retired(): ApiException
    {
        return new ApiException(
            ApiErrorCode::RESOURCE_NOT_FOUND,
            'This authentication endpoint is retired. Use the Clerk authentication flow.',
            410,
        );
    }
}
