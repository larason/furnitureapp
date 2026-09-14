<?php

namespace Tests\Unit;

use App\Support\ReferenceGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    public function test_generates_prefixed_reference_with_expected_shape(): void
    {
        $reference = ReferenceGenerator::generate('ENQ-', 10);

        $this->assertMatchesRegularExpression('/^ENQ-[A-Z0-9]{10}$/', $reference);
    }

    public function test_rejects_non_positive_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ReferenceGenerator::generate('ENQ-', 0);
    }
}
