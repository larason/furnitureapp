<?php

namespace App\Logging;

use App\Exceptions\Api\ApiException;
use App\Support\ApiErrorCode;
use App\Support\ApiLogContext;
use Illuminate\Http\Request;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class SanitizeApiExceptionLogger extends AbstractLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ?Request $request,
    ) {}

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $exception = $context['exception'] ?? null;

        if ($exception instanceof Throwable && $this->request?->is('api/*')) {
            [$code, $status] = $this->apiErrorIdentity($exception);
            $message = 'api.exception';
            $context = ApiLogContext::forException($this->request, $exception, $code, $status);
        }

        $this->logger->log($level, $message, $context);
    }

    /** @return array{0: ApiErrorCode, 1: int} */
    private function apiErrorIdentity(Throwable $exception): array
    {
        if ($exception instanceof ApiException) {
            return [$exception->errorCode(), $exception->status()];
        }

        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $code = $status >= 502 && $status <= 504
            ? ApiErrorCode::EXTERNAL_SERVICE_ERROR
            : ApiErrorCode::INTERNAL_SERVER_ERROR;

        return [$code, $status];
    }
}
