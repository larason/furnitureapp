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
    public function test_product_not_requestable_is_registered_in_the_backend_error_registry(): void
    {
        $this->assertSame('PRODUCT_NOT_REQUESTABLE', ApiErrorCode::PRODUCT_NOT_REQUESTABLE->value);
    }

    public function test_product_not_requestable_is_present_in_the_openapi_error_enum(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $enum = $document['components']['schemas']['ErrorItem']['properties']['code']['enum'];

        $this->assertContains('PRODUCT_NOT_REQUESTABLE', $enum);
        $this->assertContains('PRODUCT_NOT_PURCHASABLE', $enum);
    }
}
