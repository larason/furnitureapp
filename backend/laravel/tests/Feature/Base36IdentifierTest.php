<?php

namespace Tests\Feature;

use App\Support\Base36Identifier;
use PHPUnit\Framework\TestCase;

final class Base36IdentifierTest extends TestCase
{
    public function test_it_encodes_and_decodes_a_prefixed_positive_id(): void
    {
        $identifier = Base36Identifier::encode('prod_', 1295);

        $this->assertSame('prod_zz', $identifier);
        $this->assertSame(1295, Base36Identifier::decode($identifier, 'prod_'));
    }

    public function test_it_rejects_an_invalid_prefix_or_base36_value(): void
    {
        $this->assertNull(Base36Identifier::decode('cat_zz', 'prod_'));
        $this->assertNull(Base36Identifier::decode('prod_', 'prod_'));
        $this->assertNull(Base36Identifier::decode('prod_ABC', 'prod_'));
        $this->assertNull(Base36Identifier::decode('prod_0', 'prod_'));
    }
}
