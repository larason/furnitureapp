<?php

namespace App\Logging;

use App\Support\ApiLogContext;
use Illuminate\Http\Request;
use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;
use Throwable;

final class SanitizeApiExceptionLogs
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor($this->sanitize(...));
        }
    }

    private function sanitize(LogRecord $record): LogRecord
    {
        $exception = $record->context['exception'] ?? null;
        $request = app()->bound('request') ? request() : null;

        if (! $exception instanceof Throwable || ! $request instanceof Request || ! $request->is('api/*')) {
            return $record;
        }

        return $record->with(
            message: 'api.reportable_exception',
            context: [
                'request_id' => ApiLogContext::sanitize((string) $request->attributes->get('request_id', '')),
                'method' => ApiLogContext::sanitize($request->method()),
                'path' => ApiLogContext::sanitize('/'.$request->path()),
                'route' => ApiLogContext::sanitize((string) ($request->route()?->getName() ?? '')),
                'exception_class' => $exception::class,
            ],
        );
    }
}
