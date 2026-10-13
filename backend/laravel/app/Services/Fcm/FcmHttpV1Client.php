<?php

namespace App\Services\Fcm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

final class FcmHttpV1Client
{
    public function __construct(private readonly FcmAccessTokenProvider $accessTokenProvider) {}

    /** @param array<int|string, mixed> $data */
    public function send(string $token, array $data, string $title, string $body): FcmSendResult
    {
        $token = trim($token);
        if ($token === '') {
            throw new InvalidArgumentException('An FCM registration token is required.');
        }

        foreach ($data as $key => $value) {
            if (! is_string($key) || $key === '' || ! is_string($value)) {
                throw new InvalidArgumentException('FCM data keys and values must be non-empty strings.');
            }
        }

        $projectId = config('services.firebase.project_id');
        if (! is_string($projectId) || trim($projectId) === '') {
            throw new FcmDeliveryException('FCM project configuration is missing.', 'CONFIGURATION_FAILURE', false);
        }

        try {
            try {
                $accessToken = $this->accessTokenProvider->token()['access_token'];
            } catch (FcmDeliveryException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new FcmDeliveryException(
                    'FCM authentication failed.',
                    'AUTHENTICATION_FAILURE',
                    false,
                    false,
                    $exception,
                );
            }

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(max(1, (int) config('notifications.push.timeout_seconds', 10)))
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    [
                        'message' => [
                            'token' => $token,
                            'notification' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'data' => $data,
                        ],
                    ],
                );
        } catch (ConnectionException $exception) {
            throw new FcmDeliveryException('FCM transport failed.', 'TRANSPORT_FAILURE', true, false, $exception);
        }

        if ($response->successful()) {
            $name = $response->json('name');
            if (is_string($name) && $name !== '') {
                return new FcmSendResult($name);
            }

            throw new FcmDeliveryException('FCM returned an invalid success response.', 'PROVIDER_RESPONSE_FAILURE', true);
        }

        throw $this->providerException($response->status(), $response->json());
    }

    private function providerException(int $status, mixed $payload): FcmDeliveryException
    {
        $providerStatus = is_array($payload) ? data_get($payload, 'error.status') : null;
        $details = is_array($payload) ? data_get($payload, 'error.details') : null;
        $unregistered = is_array($details) && collect($details)->contains(
            fn (mixed $detail): bool => is_array($detail) && ($detail['errorCode'] ?? null) === 'UNREGISTERED',
        );

        if ($unregistered || $providerStatus === 'UNREGISTERED') {
            return new FcmDeliveryException('FCM registration is no longer valid.', 'TOKEN_INVALID', false, true);
        }

        if ($status === 408 || $status === 429 || $status >= 500) {
            return new FcmDeliveryException('FCM temporarily rejected the request.', 'TRANSPORT_FAILURE', true);
        }

        $category = $providerStatus === 'INVALID_ARGUMENT'
            ? 'INVALID_ARGUMENT'
            : 'PROVIDER_REJECTION';

        return new FcmDeliveryException("FCM rejected the request ({$category}).", $category, false);
    }
}
