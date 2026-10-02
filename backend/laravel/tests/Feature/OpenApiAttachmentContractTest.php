<?php

namespace Tests\Feature;

use App\Support\ApiErrorCode;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Phase 10.6 frozen-contract guard: multipart creation is represented, the
 * Attachment resource exposes the approved metadata, and attachment error
 * codes are registered. It also documents the confirmed upload-capability
 * representation gap (no creation-response token field).
 */
class OpenApiAttachmentContractTest extends TestCase
{
    public function test_request_and_enquiry_creation_document_multipart_attachment(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $request = $document['paths']['/requests']['post']['requestBody']['content'];
        $enquiry = $document['paths']['/enquiries']['post']['requestBody']['content'];

        foreach ([$request, $enquiry] as $content) {
            $this->assertArrayHasKey('multipart/form-data', $content);
        }

        $this->assertSame(
            ['type' => 'string', 'format' => 'binary', 'description' => 'Optional single inline attachment (5 MiB max; image/jpeg, image/png, image/webp, application/pdf)'],
            $document['components']['schemas']['CreateRequestMultipartRequest']['properties']['attachment'],
        );
        $this->assertSame(
            'string',
            $document['components']['schemas']['CreateEnquiryMultipartRequest']['properties']['attachment']['type'],
        );
    }

    public function test_attachment_resource_exposes_only_approved_metadata(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $attachment = $document['components']['schemas']['Attachment'];

        $this->assertSame(['id', 'filename'], $attachment['required']);
        foreach (['id', 'filename', 'content_type', 'size', 'url'] as $field) {
            $this->assertArrayHasKey($field, $attachment['properties'], "Attachment must expose {$field}.");
        }
        $this->assertSame(['string', 'null'], $attachment['properties']['url']['type']);
        $this->assertArrayNotHasKey('storage_key', $attachment['properties']);
    }

    public function test_attachment_error_codes_are_registered(): void
    {
        $this->assertSame('INVALID_ATTACHMENT', ApiErrorCode::INVALID_ATTACHMENT->value);
        $this->assertSame('ATTACHMENT_TOO_LARGE', ApiErrorCode::ATTACHMENT_TOO_LARGE->value);
        $this->assertSame('UNSUPPORTED_ATTACHMENT_TYPE', ApiErrorCode::UNSUPPORTED_ATTACHMENT_TYPE->value);

        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $enum = $document['components']['schemas']['ErrorItem']['properties']['code']['enum'];

        foreach (['INVALID_ATTACHMENT', 'ATTACHMENT_TOO_LARGE', 'UNSUPPORTED_ATTACHMENT_TYPE'] as $code) {
            $this->assertContains($code, $enum);
        }
    }

    public function test_creation_response_defines_no_upload_capability_field(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        foreach (['MadeToOrderRequest', 'Enquiry'] as $schema) {
            $properties = $document['components']['schemas'][$schema]['properties'];

            $this->assertArrayNotHasKey('upload_token', $properties);
            $this->assertArrayNotHasKey('uploadToken', $properties);
            $this->assertArrayNotHasKey('attachment_upload_token', $properties);
        }

        // The separate-upload operations exist and reference the X-Upload-Token capability.
        $this->assertArrayHasKey('/requests/{request}/attachments', $document['paths']);
        $this->assertArrayHasKey('/enquiries/{enquiry}/attachments', $document['paths']);
    }
}
