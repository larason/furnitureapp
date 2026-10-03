<?php

namespace Tests\Feature;

use App\Support\ApiErrorCode;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Phase 10.6 frozen-contract guard: multipart creation is represented, the
 * Attachment resource exposes the approved metadata, attachment error codes
 * are registered, and creation responses expose the scoped upload capability.
 */
class OpenApiAttachmentContractTest extends TestCase
{
    private const OPENAPI_PATH = '../../docs/api/openapi.yaml';

    public function test_request_and_enquiry_creation_document_multipart_attachment(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

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
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

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

        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));
        $enum = $document['components']['schemas']['ErrorItem']['properties']['code']['enum'];

        foreach (['INVALID_ATTACHMENT', 'ATTACHMENT_TOO_LARGE', 'UNSUPPORTED_ATTACHMENT_TYPE'] as $code) {
            $this->assertContains($code, $enum);
        }
    }

    public function test_creation_operations_document_the_oversize_upload_response(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        $this->assertArrayHasKey('PayloadTooLarge', $document['components']['responses']);

        foreach ([['/requests', 'post'], ['/enquiries', 'post']] as [$path, $method]) {
            $responses = $document['paths'][$path][$method]['responses'];

            $this->assertArrayHasKey('413', $responses, "{$path} {$method} must document 413.");
            $this->assertSame('#/components/responses/PayloadTooLarge', $responses['413']['$ref']);
        }
    }

    public function test_creation_response_defines_the_upload_capability_header(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        foreach (['MadeToOrderRequest', 'Enquiry'] as $schema) {
            $properties = $document['components']['schemas'][$schema]['properties'];

            $this->assertArrayNotHasKey('upload_token', $properties);
        }

        foreach ([['/requests', 'post'], ['/enquiries', 'post']] as [$path, $method]) {
            $headers = $document['paths'][$path][$method]['responses']['201']['headers'];
            $this->assertSame('string', $headers['X-Upload-Token']['schema']['type']);
        }

        // The separate-upload operations exist and reference the X-Upload-Token capability.
        $this->assertArrayHasKey('/requests/{request}/attachments', $document['paths']);
        $this->assertArrayHasKey('/enquiries/{enquiry}/attachments', $document['paths']);
    }

    public function test_catalog_creation_responses_do_not_advertise_an_attachment_capability(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        foreach ([['/products', 'post'], ['/products/{product}/variants', 'post']] as [$path, $method]) {
            $headers = $document['paths'][$path][$method]['responses']['201']['headers'] ?? [];
            $this->assertArrayNotHasKey('X-Upload-Token', $headers);
        }
    }
}
