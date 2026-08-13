<?php

namespace App\Shared\Http\Middleware;

use App\Modules\Auth\Models\User;
use App\Shared\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Accepts one or more permission slugs (OR). Laravel splits middleware
     * parameters on commas, so `permission:a,b,c` arrives as multiple args.
     */
    public function handle(Request $request, Closure $next, string ...$permissionArgs): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            throw new ApiException('Unauthenticated', 401, 'UNAUTHENTICATED');
        }

        $permissions = $user->permissionSlugs();
        $roles = $user->roleSlugs();

        if (in_array('super_admin', $roles, true)) {
            return $next($request);
        }

        $needed = [];
        foreach ($permissionArgs as $arg) {
            foreach (explode(',', $arg) as $slug) {
                $slug = trim($slug);
                if ($slug !== '') {
                    $needed[] = $slug;
                }
            }
        }

        foreach ($needed as $slug) {
            if (in_array($slug, $permissions, true)) {
                return $next($request);
            }
        }

        throw new ApiException('Forbidden', 403, 'FORBIDDEN');
    }
}
