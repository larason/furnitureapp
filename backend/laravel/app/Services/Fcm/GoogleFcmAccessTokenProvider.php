<?php

namespace App\Services\Fcm;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;

final class GoogleFcmAccessTokenProvider implements FcmAccessTokenProvider
{
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** @return array{access_token: string} */
    public function token(): array
    {
        $credentialsPath = config('services.firebase.credentials_path');
        $credentials = is_string($credentialsPath) && trim($credentialsPath) !== ''
            ? new ServiceAccountCredentials(self::FCM_SCOPE, $credentialsPath)
            : ApplicationDefaultCredentials::getCredentials(self::FCM_SCOPE);
        $configuredProject = config('services.firebase.project_id');
        $credentialProject = method_exists($credentials, 'getProjectId')
            ? $credentials->getProjectId()
            : null;
        if (is_string($configuredProject)
            && is_string($credentialProject)
            && $configuredProject !== $credentialProject) {
            throw new FcmDeliveryException(
                'FCM credential project does not match the configured project.',
                'CONFIGURATION_FAILURE',
                false,
            );
        }
        $token = $credentials->fetchAuthToken();
        $accessToken = $token['access_token'] ?? null;

        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new FcmDeliveryException(
                'FCM OAuth access token was not returned.',
                'AUTHENTICATION_FAILURE',
                false,
            );
        }

        return ['access_token' => $accessToken];
    }
}
