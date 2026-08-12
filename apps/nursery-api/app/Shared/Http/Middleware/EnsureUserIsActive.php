<?php

namespace App\Shared\Http\Middleware;

use App\Shared\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject authenticated requests when the user account is not active.
 * Must run after auth:api (or optional.jwt when a user is present).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user && ! $user->isActive()) {
            return ApiResponse::error(
                'Your account is not active. Please contact support.',
                403,
                'ACCOUNT_INACTIVE',
            );
        }

        return $next($request);
    }
}
