<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Builds the frozen V1 error envelope.
 *
 * Shape: {"errors":[{"code","message","field"?,"details"?}],"meta":{"request_id"}}.
 * No coercion of arbitrary exception properties into details; only safe,
 * explicitly supplied values are exposed.
 */
class ApiErrorResponse
{
    public function make(int $status, array $errors, Request $request, array $headers = []): JsonResponse
    {
        $requestId = $this->requestId($request);

        $response = response()->json([
            'errors' => $errors,
            'meta' => ['request_id' => $requestId],
        ], $status, $headers);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function error(
        int $status,
        ApiErrorCode $code,
        string $message,
        Request $request,
        ?string $field = null,
        array $details = [],
        array $headers = [],
    ): JsonResponse {
        return $this->make($status, [$this->errorObject($code, $message, $field, $details)], $request, $headers);
    }

    public function errorObject(ApiErrorCode $code, string $message, ?string $field = null, array $details = []): array
    {
        $error = [
            'code' => $code->value,
            'message' => $message,
        ];

        if ($field !== null) {
            $error['field'] = $field;
        }

        if ($details !== []) {
            $error['details'] = $details;
        }

        return $error;
    }

    private function requestId(Request $request): string
    {
        $requestId = $request->attributes->get('request_id');

        if (is_string($requestId) && $requestId !== '') {
            return $requestId;
        }

        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        return $requestId;
    }
}
