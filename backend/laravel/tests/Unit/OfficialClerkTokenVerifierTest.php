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

    public function test_pending_session_security_task_is_denied(): void
    {
        config(['clerk.secret_key' => 'test-secret-key']);
        config(['clerk.jwt_key' => 'test-jwt-key']);

        $payload = (object) [
            'sub' => 'user_123',
            'sid' => 'sess_123',
            'iss' => 'https://clerk.example.test',
            'sts' => 'pending',
        ];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        try {
            $verifier->verify($this->requestWithBearerToken());
            $this->fail('Expected a pending security-task session to be denied.');
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('SESSION_EXPIRED', $exception->errorCode()->value);
            $this->assertSame(401, $exception->status());
        }
    }

    public function test_active_session_without_pending_task_is_accepted(): void
    {
        config(['clerk.secret_key' => 'test-secret-key']);
        config(['clerk.jwt_key' => 'test-jwt-key']);

        $payload = (object) [
            'sub' => 'user_123',
            'sid' => 'sess_123',
            'iss' => 'https://clerk.example.test',
            'sts' => 'active',
        ];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        $identity = $verifier->verify($this->requestWithBearerToken());

        $this->assertSame('user_123', $identity->clerkUserId);
        $this->assertSame('sess_123', $identity->sessionId);
    }

    public function test_missing_session_status_is_accepted_for_compatible_clerk_tokens(): void
    {
        config(['clerk.secret_key' => 'test-secret-key']);
        config(['clerk.jwt_key' => 'test-jwt-key']);

        $payload = (object) [
            'sub' => 'user_123',
            'sid' => 'sess_123',
            'iss' => 'https://clerk.example.test',
        ];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        $identity = $verifier->verify($this->requestWithBearerToken());

        $this->assertSame('user_123', $identity->clerkUserId);
    }

    public function test_unknown_or_malformed_session_status_is_rejected(): void
    {
        config(['clerk.secret_key' => 'test-secret-key']);
        config(['clerk.jwt_key' => 'test-jwt-key']);

        foreach (['unknown', 42, new \stdClass] as $status) {
            $payload = (object) [
                'sub' => 'user_123',
                'sid' => 'sess_123',
                'iss' => 'https://clerk.example.test',
                'sts' => $status,
            ];
            $verifier = new OfficialClerkTokenVerifier(
                static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
            );

            try {
                $verifier->verify($this->requestWithBearerToken());
                $this->fail('Expected an unknown or malformed session status to be rejected.');
            } catch (ClerkAuthenticationFailure $exception) {
                $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
                $this->assertSame(401, $exception->status());
            }
        }
    }

    public function test_missing_subject_is_rejected_without_creating_an_identity(): void
    {
        config(['clerk.secret_key' => 'test-secret-key', 'clerk.jwt_key' => 'test-jwt-key']);
        $payload = (object) ['sid' => 'sess_123', 'iss' => 'https://clerk.example.test', 'sts' => 'active'];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        $this->expectException(ClerkAuthenticationFailure::class);
        try {
            $verifier->verify($this->requestWithBearerToken());
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
            $this->assertSame(401, $exception->status());
            throw $exception;
        }
    }

    public function test_wrong_configured_issuer_is_rejected(): void
    {
        config(['clerk.secret_key' => 'test-secret-key', 'clerk.jwt_key' => 'test-jwt-key', 'clerk.issuer' => 'https://trusted.example.test']);
        $payload = (object) [
            'sub' => 'user_123', 'sid' => 'sess_123', 'iss' => 'https://attacker.example.test', 'sts' => 'active',
        ];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        try {
            $verifier->verify($this->requestWithBearerToken());
            $this->fail('Expected issuer mismatch to be rejected.');
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
            $this->assertSame(401, $exception->status());
        }
    }

    public function test_wrong_audience_and_authorized_party_are_rejected(): void
    {
        config([
            'clerk.secret_key' => 'test-secret-key',
            'clerk.jwt_key' => 'test-jwt-key',
            'clerk.audiences' => ['furniture-api'],
            'clerk.authorized_parties' => ['https://shop.example.test'],
        ]);

        foreach ([
            ['aud' => 'another-api', 'azp' => 'https://shop.example.test'],
            ['aud' => 'furniture-api', 'azp' => 'https://evil.example.test'],
        ] as $claims) {
            $payload = (object) [
                'sub' => 'user_123',
                'sid' => 'sess_123',
                'iss' => 'https://clerk.example.test',
                'sts' => 'active',
                'aud' => $claims['aud'],
                'azp' => $claims['azp'],
            ];
            $verifier = new OfficialClerkTokenVerifier(
                static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
            );

            try {
                $verifier->verify($this->requestWithBearerToken());
                $this->fail('Expected invalid audience or authorized party to be rejected.');
            } catch (ClerkAuthenticationFailure $exception) {
                $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
            }
        }
    }

    public function test_absent_audience_and_authorized_party_claims_are_accepted(): void
    {
        config([
            'clerk.secret_key' => 'test-secret-key',
            'clerk.jwt_key' => 'test-jwt-key',
            'clerk.audiences' => ['furniture-api'],
            'clerk.authorized_parties' => ['https://shop.example.test'],
        ]);

        $payload = (object) [
            'sub' => 'user_123',
            'sid' => 'sess_123',
            'iss' => 'https://clerk.example.test',
            'sts' => 'active',
        ];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        $identity = $verifier->verify($this->requestWithBearerToken());

        $this->assertSame('user_123', $identity->clerkUserId);
        $this->assertSame('sess_123', $identity->sessionId);
    }

    public function test_present_audience_with_absent_authorized_party_is_accepted(): void
    {
        config([
            'clerk.secret_key' => 'test-secret-key',
            'clerk.jwt_key' => 'test-jwt-key',
            'clerk.audiences' => ['furniture-api'],
            'clerk.authorized_parties' => ['https://shop.example.test'],
        ]);

        $payload = (object) [
            'sub' => 'user_123',
            'sid' => 'sess_123',
            'iss' => 'https://clerk.example.test',
            'sts' => 'active',
            'aud' => 'furniture-api',
        ];
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedIn('token', $payload),
        );

        $identity = $verifier->verify($this->requestWithBearerToken());

        $this->assertSame('user_123', $identity->clerkUserId);
        $this->assertSame('sess_123', $identity->sessionId);
    }

    public function test_invalid_signature_state_is_rejected_before_identity_resolution(): void
    {
        config(['clerk.secret_key' => 'test-secret-key', 'clerk.jwt_key' => 'test-jwt-key']);
        $verifier = new OfficialClerkTokenVerifier(
            static fn (Request $request, AuthenticateRequestOptions $options): RequestState => RequestState::signedOut(
                new ErrorReason('token-invalid-signature', 'signature mismatch'),
            ),
        );

        try {
            $verifier->verify($this->requestWithBearerToken());
            $this->fail('Expected invalid signature to be rejected.');
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('INVALID_AUTHENTICATION', $exception->errorCode()->value);
            $this->assertSame(401, $exception->status());
        }
    }

    private function requestWithBearerToken(): Request
    {
        return Request::create('/api/v1/me', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer session-token',
        ]);
    }
}
