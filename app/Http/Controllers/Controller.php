<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractCursorPaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

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
     * Success envelope with every model passed through an API Resource. Paginators keep their
     * shape (`current_page`, `data`, `total`, ...); aggregates (numbers, grouped rows) pass unchanged.
     *
     * @param  class-string<JsonResource>  $resource
     */
    protected function resource(mixed $data, string $resource, string $message = '', int $status = 200): JsonResponse
    {
        $transform = fn (mixed $item) => $item instanceof Model ? (new $resource($item))->resolve(request()) : $item;

        $data = match (true) {
            $data instanceof AbstractPaginator, $data instanceof AbstractCursorPaginator => $data->through($transform),
            $data instanceof Collection => $data->map($transform),
            default => $transform($data),
        };

        return $this->success($data, $message, $status);
    }
}
