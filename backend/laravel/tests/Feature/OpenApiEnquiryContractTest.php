<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Phase 10.5 OpenAPI drift guard for ENQ-001: the created operation documents
 * the domain-association rejection and the customer representation matches the
 * authoritative resource definition.
 */
class OpenApiEnquiryContractTest extends TestCase
{
    public function test_enq_001_operation_documents_the_domain_association_rejection(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $responses = $document['paths']['/enquiries']['post']['responses'];

        foreach (['201', '404', '422', '429'] as $status) {
            $this->assertArrayHasKey($status, $responses, "ENQ-001 must document {$status}.");
        }
    }

    public function test_enquiry_representation_exposes_product_and_order_ids(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $properties = $document['components']['schemas']['Enquiry']['properties'];

        foreach (['product_id', 'product', 'order_id', 'order'] as $field) {
            $this->assertArrayHasKey($field, $properties, "Enquiry must expose {$field}.");
        }
    }

    public function test_enq_002_collection_uses_the_lighter_customer_summary(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $items = $document['paths']['/me/enquiries']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items'];
        $this->assertSame('#/components/schemas/CustomerEnquirySummary', $items['$ref']);

        $summary = $document['components']['schemas']['CustomerEnquirySummary'];

        foreach (['id', 'subject', 'enquiry_status', 'created_at'] as $field) {
            $this->assertArrayHasKey($field, $summary['properties'], "ENQ-002 summary must expose {$field}.");
        }
    }

    public function test_operational_enquiry_responses_use_the_operational_schema(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $collectionItems = $document['paths']['/enquiries']['get']['responses']['200']['content']['application/json']['schema']['properties']['data']['items'];
        $this->assertSame('#/components/schemas/OperationalEnquiry', $collectionItems['$ref']);

        foreach ([['/enquiries/{enquiry}', 'get'], ['/enquiries/{enquiry}/close', 'post']] as [$path, $method]) {
            $data = $document['paths'][$path][$method]['responses']['200']['content']['application/json']['schema']['properties']['data'];
            $this->assertSame('#/components/schemas/OperationalEnquiry', $data['$ref'], "{$method} {$path} must use OperationalEnquiry.");
        }

        $operational = $document['components']['schemas']['OperationalEnquiry']['allOf'][1]['properties'];
        $this->assertArrayHasKey('staff_internal_notes', $operational);
        $this->assertArrayHasKey('user_id', $operational);
        $this->assertSame('#/components/schemas/Enquiry', $document['components']['schemas']['OperationalEnquiry']['allOf'][0]['$ref']);
    }

    public function test_enquiry_multipart_documents_the_anonymous_contact_requirement(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $schema = $document['components']['schemas']['CreateEnquiryMultipartRequest'];

        // Anonymous-only requirements stay actor-conditional: authenticated
        // customers may omit name/contact because the server derives them.
        $this->assertSame(['subject', 'message'], $schema['required']);

        foreach (['name', 'email', 'phone'] as $field) {
            $this->assertStringContainsStringIgnoringCase(
                'anonymous',
                $schema['properties'][$field]['description'],
                "{$field} must document the anonymous requirement.",
            );
        }
    }

    public function test_nullable_enquiry_category_enums_include_null(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        foreach (['Enquiry', 'CustomerEnquirySummary', 'CreateEnquiryRequest', 'CreateEnquiryMultipartRequest'] as $schema) {
            $enum = $document['components']['schemas'][$schema]['properties']['category']['enum'];
            $this->assertContains(null, $enum, "{$schema}.category enum must allow a null category.");
        }
    }

    public function test_create_enquiry_request_is_a_strict_allow_list(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $request = $document['components']['schemas']['CreateEnquiryRequest'];

        $this->assertFalse($request['additionalProperties']);
        $this->assertSame(['subject', 'message'], $request['required']);
    }

    public function test_operational_enquiry_contract_matches_the_close_only_runtime_surface(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $paths = $document['paths'];

        $list = $paths['/enquiries']['get'];
        $parameters = array_map(
            static fn (array $parameter): string => $parameter['name'] ?? explode('/', $parameter['$ref'])[3],
            $list['parameters'],
        );
        $this->assertSame(['search', 'enquiry_status', 'category', 'product_id', 'order_id', 'created_from', 'created_to', 'Page', 'PerPage'], $parameters);
        $this->assertArrayHasKey('422', $list['responses']);

        $close = $paths['/enquiries/{enquiry}/close']['post'];
        $this->assertFalse($close['requestBody']['required']);
        $this->assertSame('#/components/schemas/CloseEnquiryRequest', $close['requestBody']['content']['application/json']['schema']['$ref']);
        foreach (['401', '403', '404', '422'] as $status) {
            $this->assertArrayHasKey($status, $close['responses'], "ENQ-006 must document {$status}.");
        }

        $closeRequest = $document['components']['schemas']['CloseEnquiryRequest'];
        $this->assertFalse($closeRequest['additionalProperties']);
        $this->assertSame(['staff_internal_notes'], array_keys($closeRequest['properties']));
        $this->assertSame(5000, $closeRequest['properties']['staff_internal_notes']['maxLength']);

        $attachment = $paths['/enquiries/{enquiry}/attachments']['post']['requestBody']['content']['multipart/form-data']['schema'];
        $this->assertSame(['attachment'], $attachment['required']);
        $this->assertArrayHasKey('attachment', $attachment['properties']);
        $this->assertArrayNotHasKey('file', $attachment['properties']);
    }
}
