<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate — permissions are enforced server-side, never by hiding
 * buttons (BRD non-functional requirements).
 * Usage: Route::middleware(['auth', 'role:admin'])   or   'role:manager,tl'
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && in_array($user->role->code, $roles, true),
            403
        );

        return $next($request);
    }
}
