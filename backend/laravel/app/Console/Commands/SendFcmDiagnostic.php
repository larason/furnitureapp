<?php

namespace App\Console\Commands;

use App\Services\Fcm\FcmDeliveryException;
use App\Services\Fcm\FcmHttpV1Client;
use Illuminate\Console\Command;
use Throwable;

class SendFcmDiagnostic extends Command
{
    protected $signature = 'fcm:diagnostic';

    protected $description = 'Send a local-only FCM transport diagnostic to the configured test installation.';

    public function handle(FcmHttpV1Client $client): int
    {
        if (! app()->environment(['local', 'testing']) || ! config('notifications.push.diagnostic_enabled')) {
            $this->error('FCM diagnostics are disabled outside an explicitly enabled local/testing environment.');

            return self::FAILURE;
        }

        $token = config('services.firebase.diagnostic_token');
        if (! is_string($token) || trim($token) === '') {
            $this->error('FCM_DIAGNOSTIC_TOKEN is not configured.');

            return self::FAILURE;
        }

        try {
            $result = $client->send(
                $token,
                ['kind' => 'diagnostic'],
                'SL Furnitures diagnostic',
                'FCM transport is configured.',
            );
        } catch (Throwable $exception) {
            $category = $exception instanceof FcmDeliveryException
                ? $exception->category
                : 'PROVIDER_FAILURE';
            $this->error('FCM diagnostic failed: '.$category);

            return self::FAILURE;
        }

        $this->info('FCM provider accepted the diagnostic message: '.$result->providerMessageId);

        return self::SUCCESS;
    }
}
