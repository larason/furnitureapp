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

    public function test_media_paths_and_methods_are_exactly_the_approved_set(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $actual = [];
        foreach ($document['paths'] as $path => $operations) {
            if (str_starts_with($path, '/products/{product}/images')) {
                $actual[$path] = array_values(array_filter(
                    array_keys($operations),
                    fn (string $method): bool => in_array($method, ['get', 'post', 'patch', 'put', 'delete'], true),
                ));
            }
        }

        $this->assertSame([
            '/products/{product}/images' => ['post'],
            '/products/{product}/images/{image}' => ['patch', 'delete'],
            '/products/{product}/images/reorder' => ['post'],
            '/products/{product}/images/{image}/primary' => ['post'],
        ], $actual);
    }

    public function test_media_mutations_declare_the_operational_security_and_error_contract(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $operations = [
            'CAT-015' => $document['paths']['/products/{product}/images/{image}']['delete'],
            'CAT-016' => $document['paths']['/products/{product}/images/{image}']['patch'],
            'CAT-017' => $document['paths']['/products/{product}/images/{image}/primary']['post'],
            'CAT-018' => $document['paths']['/products/{product}/images/reorder']['post'],
        ];

        foreach ($operations as $operationId => $operation) {
            $this->assertSame($operationId, $operation['operationId']);
            $this->assertSame([['bearerAuth' => []]], $operation['security']);

            foreach (['401', '403', '404', '429'] as $status) {
                $this->assertArrayHasKey($status, $operation['responses'], "{$operationId} must document {$status}");
            }

            foreach ($operation['parameters'] as $parameter) {
                $this->assertStringStartsWith('#/components/parameters/', $parameter['$ref']);
            }
        }

        $deleteResponses = $document['paths']['/products/{product}/images/{image}']['delete']['responses'];
        $primaryResponses = $document['paths']['/products/{product}/images/{image}/primary']['post']['responses'];
        $this->assertArrayHasKey('204', $deleteResponses);
        $this->assertArrayHasKey('200', $primaryResponses);
        $this->assertArrayHasKey('200', $document['paths']['/products/{product}/images/reorder']['post']['responses']);
    }

    public function test_every_media_mutation_reference_resolves(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $responseNames = ['Unauthorized', 'Forbidden', 'NotFound', 'PayloadTooLarge', 'ValidationError', 'RateLimited'];
        foreach ($responseNames as $name) {
            $this->assertArrayHasKey($name, $document['components']['responses'], "Unresolved response reference {$name}");
        }

        $parameterNames = ['ProductPath', 'ImagePath'];
        foreach ($parameterNames as $name) {
            $this->assertArrayHasKey($name, $document['components']['parameters'], "Unresolved parameter reference {$name}");
        }

        foreach (['ProductImage', 'ProductImageAltTextRequest', 'ProductImageOrderRequest'] as $name) {
            $this->assertArrayHasKey($name, $document['components']['schemas'], "Unresolved schema reference {$name}");
        }
    }

    public function test_only_the_approved_media_fields_are_writable(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $schemas = $document['components']['schemas'];

        $this->assertSame(['alt_text'], array_keys($schemas['ProductImageAltTextRequest']['properties']));
        $this->assertFalse($schemas['ProductImageAltTextRequest']['additionalProperties']);
        $this->assertSame(['string', 'null'], $schemas['ProductImageAltTextRequest']['properties']['alt_text']['type']);
        $this->assertSame(['images'], array_keys($schemas['ProductImageOrderRequest']['properties']));
        $this->assertFalse($schemas['ProductImageOrderRequest']['additionalProperties']);
        $orderPattern = $schemas['ProductImageOrderRequest']['properties']['images']['items']['pattern'];
        $this->assertSame('^img_[0-9a-z]+$', $orderPattern);

        $paths = $document['paths'];
        $this->assertArrayNotHasKey('requestBody', $paths['/products/{product}/images/{image}/primary']['post']);
        $this->assertArrayNotHasKey('requestBody', $paths['/products/{product}/images/{image}']['delete']);
        $uploadProperties = $paths['/products/{product}/images']['post']['requestBody']['content']['multipart/form-data']['schema']['properties'];
        $this->assertArrayNotHasKey('sort_order', $uploadProperties);

        foreach ($paths as $path => $operations) {
            foreach (['post', 'patch', 'put'] as $method) {
                if (! isset($operations[$method]['requestBody']['content']['application/json']['schema'])) {
                    continue;
                }

                $schema = $operations[$method]['requestBody']['content']['application/json']['schema'];

                if (isset($schema['$ref']) && str_contains((string) $schema['$ref'], 'ProductImage')) {
                    $this->assertStringNotContainsString('sort_order', json_encode($schema, JSON_THROW_ON_ERROR));
                }
            }
        }
    }

    public function test_the_image_resource_documents_nullable_alt_text_and_zero_based_legacy_positions(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $image = $document['components']['schemas']['ProductImage'];

        $this->assertSame(['string', 'null'], $image['properties']['alt_text']['type']);
        $this->assertSame(0, $image['properties']['sort_order']['minimum']);
        $this->assertSame('^img_[0-9a-z]+$', $document['components']['parameters']['ImagePath']['schema']['pattern']);
    }
}
