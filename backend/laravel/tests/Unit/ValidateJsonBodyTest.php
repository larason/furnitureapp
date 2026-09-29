<?php

namespace Tests\Unit;

use App\Exceptions\Api\ApiException;
use App\Http\Middleware\ValidateJsonBody;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

class ValidateJsonBodyTest extends TestCase
{
    public function test_it_rejects_malformed_accepted_json_body(): void
    {
        $request = Request::create(
            '/api/v1/__test__/json-body',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{broken',
        );

        $this->expectException(ApiException::class);

        (new ValidateJsonBody)->handle($request, fn () => new Response('ok'));
    }

    public function test_it_allows_valid_accepted_json_body(): void
    {
        $request = Request::create(
            '/api/v1/__test__/json-body',
            'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"value":"x"}',
        );

        $response = (new ValidateJsonBody)->handle($request, fn () => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_it_rejects_non_json_body(): void
    {
        $request = Request::create(
            '/api/v1/__test__/json-body',
            'POST',
            server: ['CONTENT_TYPE' => 'text/plain'],
            content: 'plain text',
        );

        $this->expectException(UnsupportedMediaTypeHttpException::class);

        (new ValidateJsonBody)->handle($request, fn () => new Response('ok'));
    }

    public function test_it_treats_file_only_multipart_body_as_a_body(): void
    {
        $request = Request::create(
            '/api/v1/__test__/json-body',
            'POST',
            files: ['attachment' => UploadedFile::fake()->create('invoice.pdf')],
            server: ['CONTENT_TYPE' => 'multipart/form-data; boundary=----test'],
        );

        $this->expectException(UnsupportedMediaTypeHttpException::class);

        (new ValidateJsonBody)->handle($request, fn () => new Response('ok'));
    }
}
