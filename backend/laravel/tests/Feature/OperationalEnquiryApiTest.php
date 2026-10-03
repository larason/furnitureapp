<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\EnquiryIdentifier;
use App\Support\PermissionName;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class OperationalEnquiryApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_view_staff_can_list_and_view_enquiries_without_internal_notes(): void
    {
        $enquiry = Enquiry::factory()->withStaffNote()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_1']);
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->getJson('/api/v1/enquiries')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', EnquiryIdentifier::encode($enquiry))
            ->assertJsonPath('data.0.staff_internal_notes', null);

        $this->withHeaders($headers)->getJson('/api/v1/enquiries/'.EnquiryIdentifier::encode($enquiry))
            ->assertOk()
            ->assertJsonPath('data.staff_internal_notes', null);
    }

    public function test_manage_staff_can_close_and_audit_an_enquiry(): void
    {
        $enquiry = Enquiry::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_2']);
        $headers = $this->authenticateAs($staff) + ['X-Request-Id' => '8f8f3e72-3f6c-4e4f-99ce-42e9e87b2252'];

        $this->withHeaders($headers)->postJson('/api/v1/enquiries/'.EnquiryIdentifier::encode($enquiry).'/close')
            ->assertOk()
            ->assertJsonPath('data.enquiry_status', 'CLOSED')
            ->assertJsonPath('data.staff_internal_notes', null);

        $event = AuditEvent::query()->sole();
        $this->assertSame($staff->id, $event->actor_id);
        $this->assertSame('ENQUIRY_STATUS_CHANGED', $event->action);
        $this->assertSame('enquiry', $event->resource_type);
        $this->assertSame('OPEN', $event->previous_state['enquiry_status']);
        $this->assertSame('CLOSED', $event->resulting_state['enquiry_status']);
        $this->assertSame($headers['X-Request-Id'], $event->request_id);
    }

    public function test_view_only_staff_cannot_close_and_closed_close_is_idempotent(): void
    {
        $open = Enquiry::factory()->create();
        $staff = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_3']);
        Role::findByName('STAFF')->revokePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->postJson('/api/v1/enquiries/'.EnquiryIdentifier::encode($open).'/close')
            ->assertForbidden();

        Role::findByName('STAFF')->givePermissionTo(PermissionName::ENQUIRIES_MANAGE->value);
        $manager = User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_4']);
        $managerHeaders = $this->authenticateAs($manager);
        $closed = Enquiry::factory()->closed()->create();

        $this->withHeaders($managerHeaders)->postJson('/api/v1/enquiries/'.EnquiryIdentifier::encode($closed).'/close')
            ->assertOk();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_audit_failure_rolls_back_enquiry_close(): void
    {
        $enquiry = Enquiry::factory()->create();
        $this->mock(AuditRecorder::class, function ($mock): void {
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('audit unavailable'));
        });
        $headers = $this->authenticateAs(User::factory()->staff()->create(['clerk_user_id' => 'enquiry_staff_5']));

        $this->withHeaders($headers)->postJson('/api/v1/enquiries/'.EnquiryIdentifier::encode($enquiry).'/close')
            ->assertStatus(500);

        $this->assertSame('OPEN', $enquiry->fresh()->enquiry_status->value);
        $this->assertSame(0, AuditEvent::query()->count());
    }
}
