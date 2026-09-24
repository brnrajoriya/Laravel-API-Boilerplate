<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Wrap `$data` in the standard success envelope.
     */
    protected function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return ApiResponse::success($data, $message, $status);
    }

    /**
     * Wrap `$errors` in the standard error envelope.
     *
     * @param  array<int, string>|array<string, array<int, string>>|string  $errors
     */
    protected function fail(array|string $errors, int $status = 400, ?string $message = null): JsonResponse
    {
        return ApiResponse::fail($errors, $status, $message);
    }

    /**
     * `?with=author,comments` → ['author', 'comments'] (validated by the model's `loadIncludes`).
     *
     * @return list<string>
     */
    protected function relations(Request $request): array
    {
        return str($request->string('with'))->explode(',')->map(fn ($r) => trim($r))->filter()->values()->all();
    }
}
