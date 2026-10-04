<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\AttachmentUploadCapability;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Models\User;
use App\Services\Attachments\UploadCapabilityService;
use App\Support\EnquiryIdentifier;
use App\Support\FurnitureRequestIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AuthenticatesApiUser;
use Tests\Support\CreatesAttachmentFiles;
use Tests\TestCase;

final class ScopedAttachmentUploadApiTest extends TestCase
{
    private const MULTIPART_CONTENT_TYPE = 'multipart/form-data; boundary=----test';

    private const REQUESTS_PATH = '/api/v1/requests/';

    private const ENQUIRIES_PATH = '/api/v1/enquiries/';

    private const ATTACHMENTS_PATH = '/attachments';

    private const CONTACT_NAME = 'Asha Mwangi';

    private const CONTACT_PHONE = '+255700000001';

    use AuthenticatesApiUser;
    use CreatesAttachmentFiles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['requests.route_enabled' => true, 'enquiries.route_enabled' => true]);
        Storage::fake('attachments');
    }

    public function test_anonymous_request_upload_uses_creation_capability_once(): void
    {
        $created = $this->postJson('/api/v1/requests', [
            'name' => self::CONTACT_NAME,
            'phone' => self::CONTACT_PHONE,
            'notes' => 'Please make something similar',
        ])->assertCreated();

        $identifier = $created->json('data.id');
        $token = $created->headers->get('X-Upload-Token');
        $this->assertNotNull($token);

        $headers = ['X-Upload-Token' => $token, 'Content-Type' => self::MULTIPART_CONTENT_TYPE];

        $this->withHeaders($headers)
            ->post(self::REQUESTS_PATH.$identifier.self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
            ->assertCreated()
            ->assertJsonPath('data.filename', 'reference.png');

        $this->assertDatabaseCount('attachment_upload_capabilities', 0);

        $this->withHeaders($headers)
            ->post(self::REQUESTS_PATH.$identifier.self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('second.png')])
            ->assertUnauthorized();
    }

    public function test_anonymous_enquiry_upload_uses_creation_capability(): void
    {
        $created = $this->postJson('/api/v1/enquiries', [
            'name' => self::CONTACT_NAME,
            'phone' => self::CONTACT_PHONE,
            'subject' => 'Custom dining table',
            'message' => 'I need a custom dining table for six people.',
        ])->assertCreated();

        $this->withHeaders([
            'X-Upload-Token' => $created->headers->get('X-Upload-Token'),
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ])
            ->post(self::ENQUIRIES_PATH.$created->json('data.id').self::ATTACHMENTS_PATH, ['file' => $this->pngUpload('reference.png')])
            ->assertCreated()
            ->assertJsonPath('data.content_type', 'image/png');
    }

    public function test_anonymous_request_upload_without_a_credential_does_not_reveal_identifier_existence(): void
    {
        $request = FurnitureRequest::factory()->create();
        $headers = ['Content-Type' => self::MULTIPART_CONTENT_TYPE];

        foreach ([FurnitureRequestIdentifier::encode($request), FurnitureRequestIdentifier::encodeId(999999)] as $identifier) {
            $this->withHeaders($headers)
                ->post(self::REQUESTS_PATH.$identifier.self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
                ->assertUnauthorized();
        }
    }

    public function test_anonymous_enquiry_upload_without_a_credential_does_not_reveal_identifier_existence(): void
    {
        $enquiry = Enquiry::factory()->create();
        $headers = ['Content-Type' => self::MULTIPART_CONTENT_TYPE];

        foreach ([EnquiryIdentifier::encode($enquiry), EnquiryIdentifier::encodeId(999999)] as $identifier) {
            $this->withHeaders($headers)
                ->post(self::ENQUIRIES_PATH.$identifier.self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
                ->assertUnauthorized();
        }
    }

    public function test_anonymous_request_upload_with_an_invalid_credential_does_not_reveal_identifier_existence(): void
    {
        $request = FurnitureRequest::factory()->create();
        $headers = [
            'X-Upload-Token' => 'uat_invalid',
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ];

        foreach ([FurnitureRequestIdentifier::encode($request), FurnitureRequestIdentifier::encodeId(999999)] as $identifier) {
            $this->withHeaders($headers)
                ->post(self::REQUESTS_PATH.$identifier.self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
                ->assertUnauthorized();
        }
    }

    public function test_anonymous_enquiry_upload_with_an_invalid_credential_does_not_reveal_identifier_existence(): void
    {
        $enquiry = Enquiry::factory()->create();
        $headers = [
            'X-Upload-Token' => 'uat_invalid',
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ];

        foreach ([EnquiryIdentifier::encode($enquiry), EnquiryIdentifier::encodeId(999999)] as $identifier) {
            $this->withHeaders($headers)
                ->post(self::ENQUIRIES_PATH.$identifier.self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
                ->assertUnauthorized();
        }
    }

    public function test_authenticated_customer_can_upload_only_to_owned_request(): void
    {
        $owner = User::factory()->customer()->create(['clerk_user_id' => 'upload_owner']);
        $other = User::factory()->customer()->create(['clerk_user_id' => 'upload_other']);
        $request = FurnitureRequest::factory()->for($owner, 'user')->create();
        $headers = $this->authenticateAs($other);

        $this->withHeaders([...$headers, 'Content-Type' => self::MULTIPART_CONTENT_TYPE])
            ->post(self::REQUESTS_PATH.FurnitureRequestIdentifier::encode($request).self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
            ->assertNotFound();
    }

    public function test_authenticated_customer_upload_does_not_consume_a_request_capability(): void
    {
        $owner = User::factory()->customer()->create(['clerk_user_id' => 'request_upload_owner']);
        $request = FurnitureRequest::factory()->for($owner, 'user')->create();
        $token = app(UploadCapabilityService::class)->issueForRequest((int) $request->getKey());

        $this->withHeaders([
            ...$this->authenticateAs($owner),
            'X-Upload-Token' => $token,
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ])->post(self::REQUESTS_PATH.FurnitureRequestIdentifier::encode($request).self::ATTACHMENTS_PATH, [
            'attachment' => $this->pngUpload('reference.png'),
        ])->assertCreated();

        $this->assertNull(AttachmentUploadCapability::query()->sole()->used_at);
    }

    public function test_authenticated_customer_upload_does_not_consume_an_enquiry_capability(): void
    {
        $owner = User::factory()->customer()->create(['clerk_user_id' => 'enquiry_upload_owner']);
        $enquiry = Enquiry::factory()->for($owner, 'user')->create();
        $token = app(UploadCapabilityService::class)->issueForEnquiry((int) $enquiry->getKey());

        $this->withHeaders([
            ...$this->authenticateAs($owner),
            'X-Upload-Token' => $token,
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ])->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::ATTACHMENTS_PATH, [
            'attachment' => $this->pngUpload('reference.png'),
        ])->assertCreated();

        $this->assertNull(AttachmentUploadCapability::query()->sole()->used_at);
    }

    public function test_request_upload_conflicts_before_replacing_an_existing_attachment(): void
    {
        $owner = User::factory()->customer()->create(['clerk_user_id' => 'request_attachment_owner']);
        $request = FurnitureRequest::factory()->for($owner, 'user')->create();
        Attachment::factory()->for($request, 'furnitureRequest')->create();

        $this->withHeaders([
            ...$this->authenticateAs($owner),
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ])->post(self::REQUESTS_PATH.FurnitureRequestIdentifier::encode($request).self::ATTACHMENTS_PATH, [
            'attachment' => $this->pngUpload('replacement.png'),
        ])->assertConflict()->assertJsonPath('errors.0.code', 'CONFLICT');
    }

    public function test_enquiry_upload_conflicts_before_replacing_an_existing_attachment(): void
    {
        $owner = User::factory()->customer()->create(['clerk_user_id' => 'enquiry_attachment_owner']);
        $enquiry = Enquiry::factory()->for($owner, 'user')->create();
        Attachment::factory()->forEnquiry((int) $enquiry->getKey())->create();

        $this->withHeaders([
            ...$this->authenticateAs($owner),
            'Content-Type' => self::MULTIPART_CONTENT_TYPE,
        ])->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::ATTACHMENTS_PATH, [
            'attachment' => $this->pngUpload('replacement.png'),
        ])->assertConflict()->assertJsonPath('errors.0.code', 'CONFLICT');
    }

    public function test_capability_is_bound_to_the_parent_type_and_id(): void
    {
        $request = FurnitureRequest::factory()->create();
        $enquiry = Enquiry::factory()->create();
        $token = app(UploadCapabilityService::class)->issueForRequest((int) $request->getKey());

        $this->withHeaders(['X-Upload-Token' => $token, 'Content-Type' => self::MULTIPART_CONTENT_TYPE])
            ->post(self::ENQUIRIES_PATH.EnquiryIdentifier::encode($enquiry).self::ATTACHMENTS_PATH, ['attachment' => $this->pngUpload('reference.png')])
            ->assertUnauthorized();
    }

    public function test_failed_capability_issuance_rolls_back_request_creation(): void
    {
        Schema::drop('attachment_upload_capabilities');

        $this->postJson('/api/v1/requests', [
            'name' => self::CONTACT_NAME,
            'phone' => self::CONTACT_PHONE,
            'notes' => 'Please make something similar',
        ])->assertStatus(500);

        $this->assertDatabaseCount('furniture_requests', 0);
    }

    public function test_failed_capability_issuance_rolls_back_enquiry_creation(): void
    {
        Schema::drop('attachment_upload_capabilities');

        $this->postJson('/api/v1/enquiries', [
            'name' => self::CONTACT_NAME,
            'phone' => self::CONTACT_PHONE,
            'subject' => 'Custom dining table',
            'message' => 'I need a custom dining table for six people.',
        ])->assertStatus(500);

        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_capability_is_persisted_as_an_application_keyed_digest(): void
    {
        $request = FurnitureRequest::factory()->create();
        $token = app(UploadCapabilityService::class)->issueForRequest((int) $request->getKey());

        $capability = AttachmentUploadCapability::query()->sole();

        $this->assertSame(hash_hmac('sha256', $token, (string) config('app.key')), $capability->token_hash);
    }
}
