<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Phase 7.9 OpenAPI drift guard: the machine-readable CHK-001 operation must
 * keep the frozen contract the implementation targets. This does not require a
 * database.
 */
class OpenApiCheckoutContractTest extends TestCase
{
    public function test_checkout_openapi_operation_matches_the_frozen_contract(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $operation = $document['paths']['/checkout']['post'];

        $this->assertSame('CHK-001', $operation['operationId']);

        foreach (['201', '401', '409', '422', '429'] as $status) {
            $this->assertArrayHasKey($status, $operation['responses'], "CHK-001 must document {$status}.");
        }

        // The operation must actually reference the components under test.
        $this->assertSame(
            '#/components/schemas/CheckoutRequest',
            $operation['requestBody']['content']['application/json']['schema']['$ref'] ?? null,
        );
        $this->assertSame(
            '#/components/schemas/CheckoutResponseData',
            $operation['responses']['201']['content']['application/json']['schema']['properties']['data']['$ref'] ?? null,
        );

        $request = $document['components']['schemas']['CheckoutRequest'];
        $this->assertSame(['fulfillment_type'], $request['required']);
        $this->assertArrayHasKey('delivery_address', $request['properties']);
        $this->assertFalse($request['additionalProperties']);

        $response = $document['components']['schemas']['CheckoutResponseData'];
        foreach (['order_id', 'order_reference', 'status', 'fulfillment_type', 'delivery_address', 'subtotal', 'delivery_fee', 'delivery_fee_status', 'total', 'currency', 'payment'] as $field) {
            $this->assertArrayHasKey($field, $response['properties'], "CheckoutResponseData must expose {$field}.");
        }
    }
}
