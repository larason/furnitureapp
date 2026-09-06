<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class ApiLogContext
{
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
            'exception' => $exception,
        ];
    }

    public static function sanitize(string $value): string
    {
        $value = str_replace(["\r", "\n", "\0"], ' ', $value);
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? $value;

        return trim(mb_substr($value, 0, 500));
    }
}
