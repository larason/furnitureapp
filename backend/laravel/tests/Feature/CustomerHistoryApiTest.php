<?php

namespace Tests\Feature;

use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Support\EnquiryIdentifier;
use App\Support\FurnitureRequestIdentifier;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesApiUser;
use Tests\TestCase;

final class CustomerHistoryApiTest extends TestCase
{
    use AuthenticatesApiUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
    }

    public function test_customer_request_history_is_owned_paginated_and_private(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'history_customer_1']);
        $other = User::factory()->customer()->create(['clerk_user_id' => 'history_customer_2']);
        $owned = FurnitureRequest::factory()->byUser($customer)->create(['staff_internal_notes' => 'Private staff note']);
        $foreign = FurnitureRequest::factory()->byUser($other)->create();
        FurnitureRequest::factory()->guest()->create();

        $headers = $this->authenticateAs($customer);

        $this->withHeaders($headers)->getJson('/api/v1/me/requests?per_page=1')
            ->assertOk()
            ->assertHeaderContains('Cache-Control', 'private')
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', FurnitureRequestIdentifier::encode($owned))
            ->assertJsonMissingPath('data.0.staff_internal_notes')
            ->assertJsonMissingPath('data.0.user_id');

        $this->withHeaders($headers)->getJson('/api/v1/me/requests/'.FurnitureRequestIdentifier::encode($owned))
            ->assertOk()
            ->assertJsonPath('data.id', FurnitureRequestIdentifier::encode($owned))
            ->assertJsonMissingPath('data.staff_internal_notes');

        $this->withHeaders($headers)->getJson('/api/v1/me/requests/'.FurnitureRequestIdentifier::encode($foreign))
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_customer_enquiry_history_is_owned_and_hides_internal_fields(): void
    {
        $customer = User::factory()->customer()->create(['clerk_user_id' => 'history_customer_3']);
        $other = User::factory()->customer()->create(['clerk_user_id' => 'history_customer_4']);
        $owned = Enquiry::factory()->byUser($customer)->create(['staff_internal_notes' => 'Private staff note']);
        $foreign = Enquiry::factory()->byUser($other)->create();
        Enquiry::factory()->guest()->create();

        $headers = $this->authenticateAs($customer);

        $this->withHeaders($headers)->getJson('/api/v1/me/enquiries')
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', EnquiryIdentifier::encode($owned))
            ->assertJsonMissingPath('data.0.staff_internal_notes')
            ->assertJsonMissingPath('data.0.user_id');

        $this->withHeaders($headers)->getJson('/api/v1/me/enquiries/'.EnquiryIdentifier::encode($owned))
            ->assertOk()
            ->assertJsonPath('data.id', EnquiryIdentifier::encode($owned))
            ->assertJsonMissingPath('data.staff_internal_notes');

        $this->withHeaders($headers)->getJson('/api/v1/me/enquiries/'.EnquiryIdentifier::encode($foreign))
            ->assertNotFound()
            ->assertJsonPath('errors.0.code', 'RESOURCE_NOT_FOUND');
    }

    public function test_anonymous_and_staff_cannot_use_customer_history_routes(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();

        $this->getJson('/api/v1/me/requests')->assertUnauthorized();
        $this->getJson('/api/v1/me/enquiries')->assertUnauthorized();

        $staff = User::factory()->staff()->create(['clerk_user_id' => 'history_staff_1']);
        $headers = $this->authenticateAs($staff);

        $this->withHeaders($headers)->getJson('/api/v1/me/requests')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/me/enquiries')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/me/requests/'.FurnitureRequestIdentifier::encode($request))->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/v1/me/enquiries/'.EnquiryIdentifier::encode($enquiry))->assertForbidden();
    }
}
