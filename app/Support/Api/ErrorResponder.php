<?php

namespace App\Support\Api;

use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Turns any exception into the API error envelope:
 *
 *     { "message": "...", "errors": { "field": ["..."] }, "code": "machine_readable_code" }
 *
 * `errors` is always an object (empty unless it is a validation error) so the
 * frontend can rely on its type. `code` is stable and meant for programs;
 * `message` is meant for people and may change.
 */
final class ErrorResponder
{
    public static function expectsApiResponse(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! self::expectsApiResponse($request)) {
            return null;
        }

        if ($e instanceof ValidationException) {
            return self::respond(
                $e->status,
                'validation_failed',
                'The given data was invalid.',
                $e->errors(),
            );
        }

        if ($e instanceof AuthenticationException) {
            return self::respond(Response::HTTP_UNAUTHORIZED, 'unauthenticated', 'Authentication is required.');
        }

        if ($e instanceof AuthorizationException) {
            return self::respond(Response::HTTP_FORBIDDEN, 'forbidden', 'You are not allowed to do this.');
        }

        if ($e instanceof ModelNotFoundException) {
            return self::respond(Response::HTTP_NOT_FOUND, 'not_found', 'The requested resource was not found.');
        }

        if ($e instanceof ApiException && $e->getStatusCode() < 500) {
            return self::respond($e->getStatusCode(), $e->errorCode(), $e->getMessage(), headers: $e->getHeaders());
        }

        if ($e instanceof HttpExceptionInterface) {
            return self::fromHttpException($e);
        }

        return self::serverError($e);
    }

    private static function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        if ($status >= 500) {
            return self::serverError($e, $status);
        }

        [$code, $default] = match ($status) {
            Response::HTTP_BAD_REQUEST => ['bad_request', 'The request could not be understood.'],
            Response::HTTP_UNAUTHORIZED => ['unauthenticated', 'Authentication is required.'],
            Response::HTTP_FORBIDDEN => ['forbidden', 'You are not allowed to do this.'],
            Response::HTTP_NOT_FOUND => ['not_found', 'The requested resource was not found.'],
            Response::HTTP_METHOD_NOT_ALLOWED => ['method_not_allowed', 'This method is not allowed for this endpoint.'],
            Response::HTTP_CONFLICT => ['conflict', 'The request conflicts with the current state.'],
            Response::HTTP_REQUEST_ENTITY_TOO_LARGE => ['payload_too_large', 'The request is too large.'],
            Response::HTTP_UNPROCESSABLE_ENTITY => ['unprocessable', 'The request could not be processed.'],
            Response::HTTP_TOO_MANY_REQUESTS => ['too_many_requests', 'Too many requests. Please slow down.'],
            default => ['http_error', 'The request failed.'],
        };

        // Routing exceptions carry framework wording, so only trust a message
        // that application code set on purpose (409 and 422 are ours).
        $message = in_array($status, [Response::HTTP_CONFLICT, Response::HTTP_UNPROCESSABLE_ENTITY], true)
            && $e->getMessage() !== ''
            ? $e->getMessage()
            : $default;

        return self::respond($status, $code, $message, headers: $e->getHeaders());
    }

    private static function serverError(Throwable $e, int $status = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        // Never leak internals. The exception is still reported to the log.
        $message = config('app.debug') ? $e->getMessage() : 'Something went wrong on our side.';

        return self::respond($status, 'server_error', $message);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, string>  $headers
     */
    private static function respond(int $status, string $code, string $message, array $errors = [], array $headers = []): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'errors' => $errors === [] ? new stdClass : $errors,
            'code' => $code,
        ], $status, $headers);
    }
}
