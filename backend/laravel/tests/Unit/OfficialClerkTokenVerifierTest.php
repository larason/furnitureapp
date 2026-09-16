<?php

namespace Tests\Unit;

use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\Clerk\OfficialClerkTokenVerifier;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use Clerk\Backend\Helpers\Jwks\ErrorReason;
use Clerk\Backend\Helpers\Jwks\RequestState;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Tests\TestCase;

class OfficialClerkTokenVerifierTest extends TestCase
{
    public function test_signed_out_state_reasons_map_to_the_documented_auth_errors(): void
    {
        config(['clerk.secret_key' => 'test-secret-key']);
        config(['clerk.jwt_key' => 'test-jwt-key']);

        $reasons = [
            ['session-token-missing', 'AUTHENTICATION_REQUIRED', 401],
            ['token-expired', 'SESSION_EXPIRED', 401],
            ['jwk-failed-to-load', 'EXTERNAL_SERVICE_ERROR', 503],
            ['jwk-remote-invalid', 'EXTERNAL_SERVICE_ERROR', 503],
            ['jwk-failed-to-resolve', 'EXTERNAL_SERVICE_ERROR', 503],
            ['jwk-local-invalid', 'INTERNAL_SERVER_ERROR', 500],
            ['secret-key-missing', 'INTERNAL_SERVER_ERROR', 500],
            ['token-invalid-signature', 'INVALID_AUTHENTICATION', 401],
        ];

        foreach ($reasons as [$id, $code, $status]) {
            $verifier = new OfficialClerkTokenVerifier(
                static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedOut(
                    new ErrorReason($id, 'test failure'),
                ),
            );

            try {
                $verifier->verify($this->requestWithBearerToken());
                $this->fail("Expected {$id} to fail authentication.");
            } catch (ClerkAuthenticationFailure $exception) {
                $this->assertSame($code, $exception->errorCode()->value, $id);
                $this->assertSame($status, $exception->status(), $id);
            }
        }
    }

    public function test_transport_failure_is_external_service_error(): void
    {
        $transportFailure = new ConnectException(
            'Clerk JWKS connection failed.',
            new Psr7Request('GET', 'https://clerk.example.test/jwks'),
        );
        $verifier = new OfficialClerkTokenVerifier(
            static function (Request $request, AuthenticateRequestOptions $options) use ($transportFailure): never {
                throw $transportFailure;
            },
        );

        $this->expectException(ClerkAuthenticationFailure::class);
        $this->expectExceptionCode(0);

        try {
            $verifier->verify($this->requestWithBearerToken());
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('EXTERNAL_SERVICE_ERROR', $exception->errorCode()->value);
            $this->assertSame(503, $exception->status());

            throw $exception;
        }
    }

    private function requestWithBearerToken(): Request
    {
        return Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer session-token',
        ]);
    }
}
