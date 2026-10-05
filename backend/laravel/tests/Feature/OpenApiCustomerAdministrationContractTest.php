<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiCustomerAdministrationContractTest extends TestCase
{
    public function test_adm_008_and_adm_009_are_customer_only_read_operations(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $list = $document['paths']['/users']['get'];
        $detail = $document['paths']['/users/{user}']['get'];

        $this->assertSame(['get'], array_keys($document['paths']['/users']));
        $this->assertSame(['get'], array_keys($document['paths']['/users/{user}']));
        $this->assertSame('ADM-008', $list['operationId']);
        $this->assertSame('ADM-009', $detail['operationId']);
        $this->assertSame(['#/components/parameters/Page', '#/components/parameters/PerPage'], array_column($list['parameters'], '$ref'));
        $this->assertSame('#/components/schemas/AdministrativeCustomer', $list['responses']['200']['content']['application/json']['schema']['properties']['data']['items']['$ref']);
        $this->assertSame('#/components/schemas/AdministrativeCustomer', $detail['responses']['200']['content']['application/json']['schema']['properties']['data']['$ref']);
        $this->assertSame(['id', 'role', 'name', 'email', 'phone', 'email_verified', 'created_at', 'updated_at'], array_keys($document['components']['schemas']['AdministrativeCustomer']['properties']));
    }
}
