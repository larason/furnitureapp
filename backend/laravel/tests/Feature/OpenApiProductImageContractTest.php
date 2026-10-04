<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiProductImageContractTest extends TestCase
{
    public function test_cat_009_is_a_strict_single_image_upload_returning_product_image(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $operation = $document['paths']['/products/{product}/images']['post'];
        $schema = $operation['requestBody']['content']['multipart/form-data']['schema'];

        $this->assertSame(['image'], $schema['required']);
        $this->assertFalse($schema['additionalProperties']);
        $this->assertSame(['type' => 'string', 'format' => 'binary'], $schema['properties']['image']);
        $this->assertSame('#/components/schemas/ProductImage', $operation['responses']['201']['content']['application/json']['schema']['properties']['data']['$ref']);
        $this->assertSame(['id', 'url', 'alt_text', 'sort_order', 'is_primary'], array_keys($document['components']['schemas']['ProductImage']['properties']));
    }
}
