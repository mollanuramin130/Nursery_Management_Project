<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) ($request->header('X-Request-Id') ?? '');
        // Prevent log injection / oversized spoofed IDs.
        $requestId = preg_match('/^[A-Za-z0-9_.:\-]{8,80}$/', $incoming)
            ? $incoming
            : ('req_'.bin2hex(random_bytes(8)));
        $request->attributes->set('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
