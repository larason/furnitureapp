<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class OpenApiAuditLogContractTest extends TestCase
{
    public function test_adm_007_is_a_read_only_paginated_audit_collection(): void
    {
        $document = Yaml::parseFile(base_path('../../docs/api/openapi.yaml'));
        $operation = $document['paths']['/admin/audit-logs']['get'];
        $schema = $document['components']['schemas']['AuditLog'];

        $this->assertSame(['get'], array_keys($document['paths']['/admin/audit-logs']));
        $this->assertSame('ADM-007', $operation['operationId']);
        $this->assertSame(['actor', 'action', 'resource_type', 'resource_id', 'created_from', 'created_to', 'page', 'per_page'], array_map(
            fn (array $parameter): string => $parameter['name'] ?? ($parameter['$ref'] === '#/components/parameters/Page' ? 'page' : 'per_page'),
            $operation['parameters'],
        ));
        $this->assertSame(['id', 'actor_id', 'actor_role', 'action', 'resource_type', 'resource_id', 'timestamp', 'previous_state', 'resulting_state', 'request_id'], $schema['required']);
        $this->assertSame(['object', 'null'], $schema['properties']['previous_state']['type']);
        $this->assertSame(['object', 'null'], $schema['properties']['resulting_state']['type']);
    }
}
