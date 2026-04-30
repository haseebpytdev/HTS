<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $roleValue = is_object($user->role) && property_exists($user->role, 'value')
            ? $user->role->value
            : (string) $user->role;

        if (! in_array($roleValue, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
