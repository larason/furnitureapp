<?php

namespace Tests\Unit;

use App\Support\JsonMediaType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JsonMediaTypeTest extends TestCase
{
    #[DataProvider('acceptedTypes')]
    public function test_accepts_valid_json_media_types(string $contentType): void
    {
        $this->assertTrue(JsonMediaType::accepts($contentType));
    }

    #[DataProvider('rejectedTypes')]
    public function test_rejects_other_media_types(?string $contentType): void
    {
        $this->assertFalse(JsonMediaType::accepts($contentType));
    }

    /** @return array<string, array{0: string}> */
    public static function acceptedTypes(): array
    {
        return [
            'plain json' => ['application/json'],
            'json with charset' => ['application/json; charset=utf-8'],
            'vendor suffix' => ['application/vnd.api+json'],
            'ld suffix' => ['application/ld+json'],
            'uppercase' => ['Application/JSON'],
            'leading allowed char' => ['application/vnd.api+json'],
        ];
    }

    /** @return array<string, array{0: ?string}> */
    public static function rejectedTypes(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'bare suffix' => ['+json'],
            'empty type' => ['/json'],
            'empty subtype suffix' => ['application/+json'],
            'subtype with space' => ['application/vnd foo+json'],
            'subtype separator' => ['application/vnd,api+json'],
            'type with space' => ['app lication/vnd.api+json'],
            'extra segments' => ['application/vnd/api+json'],
            'jsonp' => ['application/jsonp'],
            'plain text' => ['text/plain'],
            'form' => ['application/x-www-form-urlencoded'],
            'starts with punctuation' => ['!application/x+json'],
            'starts with dash' => ['-application/x+json'],
            'name exceeds 127' => ['application/'.str_repeat('a', 128).'+json'],
        ];
    }
}
