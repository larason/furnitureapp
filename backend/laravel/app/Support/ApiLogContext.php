<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ApiLogContext
{
    private const MAX_TRACE_FRAMES = 15;

    public static function forException(Request $request, Throwable $exception, ApiErrorCode $code, int $status): array
    {
        $requestId = $request->attributes->get('request_id');

        if (! is_string($requestId) || $requestId === '') {
            $requestId = (string) Str::uuid();
            $request->attributes->set('request_id', $requestId);
        }

        return [
            'request_id' => self::sanitize($requestId),
            'method' => self::sanitize($request->method()),
            'path' => self::sanitize('/'.$request->path()),
            'route' => self::sanitize((string) ($request->route()?->getName() ?? '')),
            'status' => $status,
            'code' => $code->value,
            'exception_class' => $exception::class,
            'exception_fingerprint' => self::fingerprint($exception),
            'exception_trace' => self::sanitize(self::trace($exception)),
        ];
    }

    /**
     * Stable, non-reversible discriminator for failures of the same class. The
     * raw message is never persisted; the keyed hash lets operators correlate
     * identical failures without retaining provider or customer content.
     */
    private static function fingerprint(Throwable $exception): string
    {
        $key = (string) config('app.key');

        return substr(hash_hmac('sha256', $exception::class.'|'.$exception->getMessage(), $key), 0, 16);
    }

    /**
     * Builds an argument-free, basename-only trace so failures of the same
     * class remain distinguishable without persisting full paths or call args.
     */
    private static function trace(Throwable $exception): string
    {
        $frames = [basename($exception->getFile()).':'.$exception->getLine()];

        foreach ($exception->getTrace() as $frame) {
            if (count($frames) > self::MAX_TRACE_FRAMES) {
                break;
            }

            $frames[] = self::frame($frame);
        }

        return implode(' <- ', array_filter($frames));
    }

    /** @param array<string, mixed> $frame */
    private static function frame(array $frame): string
    {
        $call = (string) ($frame['class'] ?? '').(string) ($frame['type'] ?? '').(string) ($frame['function'] ?? '');
        $location = isset($frame['file'], $frame['line'])
            ? basename((string) $frame['file']).':'.$frame['line']
            : '';

        return trim($call.' '.$location);
    }

    public static function sanitize(string $value): string
    {
        $value = str_replace(["\r", "\n", "\0"], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        return trim(mb_substr($value, 0, 500));
    }
}
