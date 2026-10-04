<?php

namespace Tests\Feature;

use App\Support\ApiErrorCode;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Phase 10.4 frozen-contract consistency guard: `PRODUCT_NOT_REQUESTABLE` is
 * defined by the normative REQ-001 contract and must be present in both the
 * backend closed registry and the OpenAPI global error enum.
 */
class OpenApiRequestContractTest extends TestCase
{
    private const OPENAPI_PATH = '../../docs/api/openapi.yaml';

    public function test_product_not_requestable_is_registered_in_the_backend_error_registry(): void
    {
        $this->assertSame('PRODUCT_NOT_REQUESTABLE', ApiErrorCode::PRODUCT_NOT_REQUESTABLE->value);
    }

    public function test_product_not_requestable_is_present_in_the_openapi_error_enum(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        $enum = $document['components']['schemas']['ErrorItem']['properties']['code']['enum'];

        $this->assertContains('PRODUCT_NOT_REQUESTABLE', $enum);
        $this->assertContains('PRODUCT_NOT_PURCHASABLE', $enum);
    }

    public function test_req_001_operation_documents_product_linking_rejections(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        $responses = $document['paths']['/requests']['post']['responses'];

        foreach (['201', '404', '409', '422', '429'] as $status) {
            $this->assertArrayHasKey($status, $responses, "REQ-001 must document {$status}.");
        }
    }

    public function test_request_creation_requires_at_least_one_contact_method(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        foreach (['CreateRequestRequest', 'CreateRequestMultipartRequest'] as $schema) {
            $anyOf = $document['components']['schemas'][$schema]['anyOf'];
            $this->assertSame(
                [['required' => ['phone']], ['required' => ['email']]],
                $anyOf,
                "{$schema} must require at least one of phone or email.",
            );
        }
    }

    public function test_req_002_collection_uses_the_lighter_customer_summary(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        $items = $document['paths']['/me/requests']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items'];
        $this->assertSame('#/components/schemas/CustomerFurnitureRequestSummary', $items['$ref']);

        $summary = $document['components']['schemas']['CustomerFurnitureRequestSummary'];

        foreach (['id', 'product_id', 'quantity', 'request_status', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $summary['properties'], "REQ-002 summary must expose {$field}.");
        }
    }

    public function test_req_001_multipart_documents_bracket_encoded_dimensions(): void
    {
        $document = Yaml::parseFile(base_path(self::OPENAPI_PATH));

        $properties = $document['components']['schemas']['CreateRequestMultipartRequest']['properties'];

        foreach (['dimensions[length]', 'dimensions[width]', 'dimensions[height]', 'dimensions[unit]'] as $field) {
            $this->assertArrayHasKey($field, $properties, "REQ-001 multipart must document {$field}.");
        }

        $this->assertArrayNotHasKey('dimensions', $properties);

        foreach (['dimensions[length]', 'dimensions[width]', 'dimensions[height]'] as $measurement) {
            $this->assertSame(['number', 'null'], $properties[$measurement]['type']);
        }

        $this->assertSame('string', $properties['dimensions[unit]']['type']);
        $this->assertSame(['cm'], $properties['dimensions[unit]']['enum']);
    }
}
