<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;

class AuthController extends V1Controller
{
    public function register(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function login(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function logout(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function changePassword(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function passwordForgot(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function passwordReset(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function verifyEmail(): JsonResponse
    {
        return $this->notImplemented();
    }

    public function resendEmailVerification(): JsonResponse
    {
        return $this->notImplemented();
    }
}
