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

    public function test_create_enquiry_request_is_a_strict_allow_list(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $request = $document['components']['schemas']['CreateEnquiryRequest'];

        $this->assertFalse($request['additionalProperties']);
        $this->assertSame(['subject', 'message'], $request['required']);
    }
}
