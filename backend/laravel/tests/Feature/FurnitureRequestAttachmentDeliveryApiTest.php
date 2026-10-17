<?php

namespace Tests\Feature;

use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Enquiry;
use App\Models\FurnitureRequest;
use App\Support\AttachmentIdentifier;
use App\Support\FurnitureRequestIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

final class FurnitureRequestAttachmentDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\nattachment delivery body\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('attachments');
    }

    public function test_a_valid_signed_url_streams_the_stored_bytes_with_private_headers(): void
    {
        [$request, $attachment, $url] = $this->storedAttachment();

        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame(self::PDF, $response->streamedContent());
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment;', $disposition);
        $this->assertStringContainsString('spec-sheet.pdf', $disposition);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $this->assertStringNotContainsString((string) $attachment->storage_key, $url);
        $this->assertStringNotContainsString('attachments/requests', $url);
        $this->assertStringNotContainsString((string) $attachment->storage_key, $response->streamedContent());
    }

    public function test_an_unsigned_url_is_rejected(): void
    {
        [$request, $attachment] = $this->storedAttachment();

        $this->get('/api/v1/requests/'.FurnitureRequestIdentifier::encode($request)
            .'/attachments/'.AttachmentIdentifier::encode($attachment))
            ->assertForbidden();
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        [, , $url] = $this->storedAttachment();

        $this->get($url.'&signature=0000000000000000000000000000000000000000000000000000000000000000')
            ->assertForbidden();
    }

    public function test_an_expired_signed_url_is_rejected(): void
    {
        [$request, $attachment] = $this->storedAttachment();

        $expired = $this->signedUrl($request, $attachment, now()->subMinute());

        $this->get($expired)->assertForbidden();
    }

    public function test_an_attachment_belonging_to_another_request_is_masked_with_404(): void
    {
        [$request] = $this->storedAttachment();
        [, $otherAttachment] = $this->storedAttachment();

        $url = $this->signedUrl($request, $otherAttachment);

        $this->get($url)->assertNotFound();
    }

    public function test_unknown_identifiers_are_masked_with_404_without_leaking_internals(): void
    {
        $url = URL::temporarySignedRoute('api.requests.attachments.download', now()->addMinutes(5), [
            'request' => 'req_zzzzzzzz',
            'attachment' => 'att_zzzzzzzz',
        ]);

        $response = $this->get($url)->assertNotFound();

        $this->assertStringNotContainsString('req_zzzzzzzz', (string) $response->getContent());
        $this->assertStringNotContainsString('att_zzzzzzzz', (string) $response->getContent());
    }

    public function test_attachment_url_is_signed_for_a_request_and_null_when_the_parent_has_no_route(): void
    {
        [$request, $attachment] = $this->storedAttachment();

        $requestData = (new AttachmentResource($attachment))->resolve();
        $this->assertIsString($requestData['url']);
        $this->assertStringContainsString('signature=', $requestData['url']);
        $this->assertStringContainsString(FurnitureRequestIdentifier::encode($request), $requestData['url']);

        $enquiry = Enquiry::factory()->create();
        $enquiryAttachment = Attachment::factory()->forEnquiry((int) $enquiry->getKey())->create();
        $enquiryData = (new AttachmentResource($enquiryAttachment))->resolve();
        $this->assertNull($enquiryData['url']);
    }

    /** @return array{FurnitureRequest, Attachment, string} */
    private function storedAttachment(): array
    {
        $request = FurnitureRequest::factory()->create();
        $key = 'attachments/requests/'.Str::ulid().'.pdf';
        Storage::disk('attachments')->put($key, self::PDF);

        $attachment = Attachment::factory()->create([
            'furniture_request_id' => $request->getKey(),
            'enquiry_id' => null,
            'storage_disk' => 'attachments',
            'storage_key' => $key,
            'filename' => 'spec-sheet.pdf',
            'content_type' => 'application/pdf',
            'size' => strlen(self::PDF),
        ]);

        return [$request, $attachment, $this->signedUrl($request, $attachment)];
    }

    private function signedUrl(FurnitureRequest $request, Attachment $attachment, ?\DateTimeInterface $expiry = null): string
    {
        return URL::temporarySignedRoute(
            'api.requests.attachments.download',
            $expiry ?? now()->addMinutes(5),
            [
                'request' => FurnitureRequestIdentifier::encode($request),
                'attachment' => AttachmentIdentifier::encode($attachment),
            ],
        );
    }
}
