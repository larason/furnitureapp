<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiInventoryContractTest extends TestCase
{
    public function test_inventory_contract_has_only_canonical_operations_and_requires_updated_at(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));

        $this->assertSame(['get'], array_keys($document['paths']['/inventory']));
        $this->assertSame(['get'], array_keys($document['paths']['/inventory/{inventory}']));
        $this->assertSame(['post'], array_keys($document['paths']['/inventory/{inventory}/adjust']));
        $this->assertContains('updated_at', $document['components']['schemas']['Inventory']['required']);
    }
}
