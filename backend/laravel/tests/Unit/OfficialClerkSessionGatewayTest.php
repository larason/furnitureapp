<?php

namespace Tests\Unit;

use App\Authentication\Clerk\OfficialClerkSessionGateway;
use Clerk\Backend\ClerkBackend;
use Clerk\Backend\Models\Components\Session;
use Clerk\Backend\Models\Components\SessionObject;
use Clerk\Backend\Models\Components\Status;
use Clerk\Backend\Models\Operations\RevokeSessionResponse;
use Clerk\Backend\Sessions;
use GuzzleHttp\Psr7\Response as Psr7Response;
use RuntimeException;
use Tests\TestCase;

class OfficialClerkSessionGatewayTest extends TestCase
{
    public function test_revoke_maps_to_clerk_session_revoke(): void
    {
        $session = new Session(SessionObject::Session, 'sess_123', 'user_123', 'client_123', Status::Revoked, 100, 200, 300, 100, 100);
        $response = new RevokeSessionResponse('application/json', 200, new Psr7Response(200), $session);

        $sessions = $this->mock(Sessions::class);
        $sessions->shouldReceive('revoke')->once()->with('sess_123')->andReturn($response);

        $backend = $this->mock(ClerkBackend::class);
        $backend->sessions = $sessions;

        $gateway = new OfficialClerkSessionGateway($backend);

        $gateway->revoke('sess_123');

        $this->assertTrue(true);
    }

    public function test_active_session_response_is_treated_as_revocation_failure(): void
    {
        $session = new Session(SessionObject::Session, 'sess_123', 'user_123', 'client_123', Status::Active, 100, 200, 300, 100, 100);
        $response = new RevokeSessionResponse('application/json', 200, new Psr7Response(200), $session);

        $sessions = $this->mock(Sessions::class);
        $sessions->shouldReceive('revoke')->once()->with('sess_123')->andReturn($response);

        $backend = $this->mock(ClerkBackend::class);
        $backend->sessions = $sessions;

        $gateway = new OfficialClerkSessionGateway($backend);

        $this->expectException(RuntimeException::class);
        $gateway->revoke('sess_123');
    }

    public function test_revoke_failure_raises_runtime_exception(): void
    {
        $sessions = $this->mock(Sessions::class);
        $sessions->shouldReceive('revoke')->once()->andThrow(new RuntimeException('Clerk API error'));

        $backend = $this->mock(ClerkBackend::class);
        $backend->sessions = $sessions;

        $gateway = new OfficialClerkSessionGateway($backend);

        $this->expectException(RuntimeException::class);
        $gateway->revoke('sess_123');
    }
}
