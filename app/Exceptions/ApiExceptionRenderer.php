<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders every exception on `api/*` routes in the standard envelope, so controllers do not need
 * try / catch blocks. Internal error details are only exposed when `APP_DEBUG=true`.
 */
final class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return null; // let Laravel render it (web routes)
        }

        return match (true) {
            $e instanceof ValidationException => ApiResponse::fail($e->errors(), 422, $e->getMessage()),
            $e instanceof AuthenticationException => ApiResponse::fail('Unauthenticated.', 401),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiResponse::fail('Forbidden', 403),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::fail('Resource not found.', 404),
            $e instanceof MethodNotAllowedHttpException => ApiResponse::fail('Method not allowed.', 405),
            $e instanceof TooManyRequestsHttpException => ApiResponse::fail('Too many requests. Please slow down.', 429)
                ->withHeaders($e->getHeaders()),
            $e instanceof HttpExceptionInterface => ApiResponse::fail(
                $e->getMessage() ?: 'Request failed.',
                $e->getStatusCode(),
            )->withHeaders($e->getHeaders()),
            default => ApiResponse::fail(
                config('app.debug') ? [$e->getMessage()] : ['Server error. Please try again later.'],
                500,
            ),
        };
    }
}
