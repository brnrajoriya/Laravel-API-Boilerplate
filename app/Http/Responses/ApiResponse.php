<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use stdClass;

/**
 * The single JSON envelope used by every endpoint (success and error):
 *
 *   {
 *     "status":   "success" | "fail",
 *     "data":     {...} | [...] | number,   // {} when there is nothing to return
 *     "errors":   {} | ["message"] | { "field": ["message"] },
 *     "hasError": false | true,
 *     "message":  "Human readable summary"
 *   }
 */
final class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return self::make('success', $data, new stdClass, false, $message, $status);
    }

    /**
     * @param  array<int, string>|array<string, array<int, string>>|string  $errors
     *                                                                               A list of messages, or `field => messages` for validation errors.
     */
    public static function fail(array|string $errors, int $status = 400, ?string $message = null): JsonResponse
    {
        $errors = is_string($errors) ? [$errors] : $errors;
        $message ??= self::firstMessage($errors);

        return self::make('fail', null, $errors === [] ? new stdClass : $errors, true, $message, $status);
    }

    private static function make(
        string $status,
        mixed $data,
        mixed $errors,
        bool $hasError,
        string $message,
        int $httpStatus,
    ): JsonResponse {
        return new JsonResponse([
            'status' => $status,
            'data' => self::normalize($data),
            'errors' => $errors,
            'hasError' => $hasError,
            'message' => $message,
        ], $httpStatus);
    }

    private static function normalize(mixed $data): mixed
    {
        return match (true) {
            $data === null => new stdClass,
            $data instanceof JsonResource => $data->response()->getData(true),
            $data instanceof Arrayable => $data->toArray(),
            default => $data,
        };
    }

    /**
     * @param  array<int|string, mixed>  $errors
     */
    private static function firstMessage(array $errors): string
    {
        $first = reset($errors);

        return match (true) {
            is_array($first) => (string) (reset($first) ?: ''),
            is_string($first) => $first,
            default => '',
        };
    }
}
