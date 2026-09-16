<?php

namespace Tests\Unit;

use App\Authentication\Clerk\ClerkAuthenticationFailure;
use App\Authentication\Clerk\OfficialClerkTokenVerifier;
use Clerk\Backend\Helpers\Jwks\AuthenticateRequestOptions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Tests\TestCase;

class OfficialClerkTokenVerifierTest extends TestCase
{
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
            $verifier->verify(Request::create('/api/v1/me', 'GET', [], [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer session-token',
            ]));
        } catch (ClerkAuthenticationFailure $exception) {
            $this->assertSame('EXTERNAL_SERVICE_ERROR', $exception->errorCode()->value);
            $this->assertSame(503, $exception->status());

            throw $exception;
        }
    }
}
