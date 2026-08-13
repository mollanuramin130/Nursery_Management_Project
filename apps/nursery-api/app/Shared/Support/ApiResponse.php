<?php

namespace App\Shared\Support;

use App\Shared\Exceptions\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiResponse
{
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
            'meta' => self::baseMeta($meta),
        ], $status);
    }

    public static function error(
        string $message,
        int $status = 400,
        ?string $errorCode = null,
        mixed $errors = null,
        mixed $data = null,
        array $meta = [],
    ): JsonResponse {
        $meta = array_merge([
            'error_code' => $errorCode ?? self::defaultErrorCode($status),
        ], $meta);

        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
            'meta' => self::baseMeta($meta),
        ], $status);
    }

    public static function fromException(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return self::error(
                message: 'Validation failed',
                status: 422,
                errorCode: 'VALIDATION_ERROR',
                errors: $e->errors(),
            );
        }

        if ($e instanceof ApiException) {
            return self::error(
                message: $e->getMessage(),
                status: $e->getStatusCode(),
                errorCode: $e->errorCode(),
                errors: $e->errors(),
                data: $e->data(),
            );
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() !== '' ? $e->getMessage() : self::defaultMessage($status);

            // Replace Laravel's terse "Too Many Attempts." with actionable copy.
            if ($status === 429) {
                $message = self::friendlyRateLimitMessage();
            }

            return self::error(
                message: $message,
                status: $status,
                errorCode: self::defaultErrorCode($status),
            );
        }

        $message = config('app.debug')
            ? $e->getMessage()
            : 'An unexpected error occurred';

        return self::error(
            message: $message,
            status: 500,
            errorCode: 'SERVER_ERROR',
        );
    }

    private static function baseMeta(array $extra = []): array
    {
        return array_merge([
            'request_id' => request()->attributes->get('request_id')
                ?? request()->header('X-Request-Id')
                ?? ('req_'.bin2hex(random_bytes(8))),
            'timestamp' => now()->timezone(config('app.timezone', 'Asia/Kolkata'))->toIso8601String(),
        ], $extra);
    }

    private static function defaultErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMITED',
            502 => 'EXTERNAL_API_ERROR',
            default => 'SERVER_ERROR',
        };
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Unauthenticated',
            403 => 'Forbidden',
            404 => 'Not found',
            409 => 'Conflict',
            422 => 'Validation failed',
            429 => 'Please wait about a minute, then try again.',
            default => 'Request failed',
        };
    }

    private static function friendlyRateLimitMessage(): string
    {
        $path = (string) request()->path();

        if (request()->isMethod('POST') && preg_match('#(^|/)orders$#', $path) === 1) {
            return 'You tried to place an order too many times. Please wait about a minute, then try again.';
        }

        if (str_contains($path, 'auth/login') || str_contains($path, 'auth/register')) {
            return 'Too many sign-in attempts. Please wait about a minute, then try again.';
        }

        if (str_contains($path, 'checkout')) {
            return 'Checkout is temporarily limited. Please wait about a minute, then try again.';
        }

        return 'You\'re doing that too quickly. Please wait about a minute, then try again.';
    }
}
