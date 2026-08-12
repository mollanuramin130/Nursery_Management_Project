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

        // Baseline DoS protection for all API routes (auth/payment keep stricter route throttles).
        $middleware->throttleApi('120,1');

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
