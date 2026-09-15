<?php

namespace Tests\Unit;

use App\Authentication\Clerk\OfficialClerkUserGateway;
use Clerk\Backend\Models\Components\VerificationStatus;
use ReflectionMethod;
use stdClass;
use Tests\TestCase;

class OfficialClerkUserGatewayTest extends TestCase
{
    public function test_verified_status_is_mapped_to_true(): void
    {
        $this->assertTrue($this->emailVerified(VerificationStatus::Verified));
    }

    public function test_non_verified_status_is_mapped_to_false(): void
    {
        $this->assertFalse($this->emailVerified(VerificationStatus::Unverified));
    }

    private function emailVerified(VerificationStatus $status): bool
    {
        $emailAddress = new stdClass;
        $emailAddress->emailAddress = 'customer@example.com';
        $emailAddress->verification = new stdClass;
        $emailAddress->verification->status = $status;

        $user = new stdClass;
        $user->emailAddresses = [$emailAddress];

        $gateway = (new \ReflectionClass(OfficialClerkUserGateway::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($gateway, 'emailVerified');

        return $method->invoke($gateway, $user, 'customer@example.com');
    }
}
