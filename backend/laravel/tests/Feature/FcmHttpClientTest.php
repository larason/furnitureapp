<?php

namespace Tests\Feature;

use App\Services\Fcm\FcmAccessTokenProvider;
use App\Services\Fcm\FcmDeliveryException;
use App\Services\Fcm\FcmHttpClient;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

final class FcmHttpClientTest extends TestCase
{
    public function test_it_sends_a_minimal_http_v1_message_with_a_short_lived_access_token(): void
    {
        config(['services.firebase.project_id' => 'slfurnitures']);
        $provider = Mockery::mock(FcmAccessTokenProvider::class);
        $provider->expects('token')->once()->andReturn(['access_token' => 'oauth-token']);
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/slfurnitures/messages/1'], 200),
        ]);

        $result = (new FcmHttpClient($provider))->send(
            'registration-token',
            ['kind' => 'diagnostic'],
            'Test title',
            'Test body',
        );

        $this->assertSame('projects/slfurnitures/messages/1', $result->providerMessageId);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/slfurnitures/messages:send'
                && $request->hasHeader('Authorization', 'Bearer oauth-token')
                && $request['message']['token'] === 'registration-token'
                && $request['message']['data'] === ['kind' => 'diagnostic'];
        });
    }

    public function test_unregistered_tokens_are_classified_without_exposing_the_token(): void
    {
        config(['services.firebase.project_id' => 'slfurnitures']);
        $provider = Mockery::mock(FcmAccessTokenProvider::class);
        $provider->expects('token')->once()->andReturn(['access_token' => 'oauth-token']);
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [['errorCode' => 'UNREGISTERED']],
                ],
            ], 404),
        ]);

        try {
            (new FcmHttpClient($provider))->send('secret-registration-token', [], 'Test title', 'Test body');
            $this->fail('Expected an FCM delivery exception.');
        } catch (FcmDeliveryException $exception) {
            $this->assertSame('TOKEN_INVALID', $exception->category);
            $this->assertTrue($exception->tokenInvalid);
            $this->assertStringNotContainsString('secret-registration-token', $exception->getMessage());
        }
    }

    public function test_invalid_argument_is_not_classified_as_an_invalid_token(): void
    {
        config(['services.firebase.project_id' => 'slfurnitures']);
        $provider = Mockery::mock(FcmAccessTokenProvider::class);
        $provider->expects('token')->once()->andReturn(['access_token' => 'oauth-token']);
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response([
                'error' => ['status' => 'INVALID_ARGUMENT'],
            ], 400),
        ]);

        try {
            (new FcmHttpClient($provider))->send('registration-token', [], 'Test title', 'Test body');
            $this->fail('Expected an FCM delivery exception.');
        } catch (FcmDeliveryException $exception) {
            $this->assertSame('INVALID_ARGUMENT', $exception->category);
            $this->assertFalse($exception->tokenInvalid);
            $this->assertFalse($exception->transient);
        }
    }

    public function test_transient_provider_failures_are_retryable(): void
    {
        config(['services.firebase.project_id' => 'slfurnitures']);
        $provider = Mockery::mock(FcmAccessTokenProvider::class);
        $provider->expects('token')->once()->andReturn(['access_token' => 'oauth-token']);
        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503),
        ]);

        try {
            (new FcmHttpClient($provider))->send('registration-token', [], 'Test title', 'Test body');
            $this->fail('Expected an FCM delivery exception.');
        } catch (FcmDeliveryException $exception) {
            $this->assertSame('TRANSPORT_FAILURE', $exception->category);
            $this->assertTrue($exception->transient);
            $this->assertFalse($exception->tokenInvalid);
        }
    }

    public function test_missing_project_configuration_fails_before_oauth_or_http(): void
    {
        config(['services.firebase.project_id' => null]);
        $provider = Mockery::mock(FcmAccessTokenProvider::class);
        $provider->expects('token')->never();
        Http::fake();

        try {
            (new FcmHttpClient($provider))->send('registration-token', [], 'Test title', 'Test body');
            $this->fail('Expected an FCM delivery exception.');
        } catch (FcmDeliveryException $exception) {
            $this->assertSame('FCM project configuration is missing.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
