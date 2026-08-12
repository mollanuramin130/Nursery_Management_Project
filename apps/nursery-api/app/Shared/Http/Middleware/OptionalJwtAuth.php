<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class OptionalJwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            if ($request->bearerToken()) {
                $user = JWTAuth::parseToken()->authenticate();
                if ($user) {
                    auth('api')->setUser($user);
                }
            }
        } catch (\Throwable) {
            // Public cart routes tolerate missing/invalid tokens.
        }

        return $next($request);
    }
}
