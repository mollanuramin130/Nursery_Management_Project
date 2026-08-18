<?php

use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Http\Middleware\ForceJsonResponse;
use App\Shared\Http\Middleware\SecurityHeaders;
use App\Shared\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            AssignRequestId::class,
            SecurityHeaders::class,
        ]);

        // Named limiter `api` — env-aware ceilings in config/throttling.php (local/testing relaxed).
        $middleware->throttleApi();

        $middleware->alias([
            'optional.jwt' => \App\Shared\Http\Middleware\OptionalJwtAuth::class,
            'permission' => \App\Shared\Http\Middleware\EnsurePermission::class,
            'active.user' => \App\Shared\Http\Middleware\EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Pass through safe auth-flow messages; never leak internals.
            $message = trim($e->getMessage());
            $safeCodes = [
                'Invalid email or password' => 'AUTH_INVALID_CREDENTIALS',
                'Account is blocked' => 'AUTH_ACCOUNT_BLOCKED',
                'Invalid or expired refresh token' => 'AUTH_REFRESH_INVALID',
            ];

            if (isset($safeCodes[$message])) {
                return ApiResponse::error($message, 401, $safeCodes[$message]);
            }

            return ApiResponse::error('Unauthenticated', 401, 'UNAUTHENTICATED');
        });

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return null;
            }

            return ApiResponse::fromException($e);
        });
    })->create();
