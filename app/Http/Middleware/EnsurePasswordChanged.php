<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** A temporary password must be rotated before anything else works (BRD §18). */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->must_change_password
            && ! $request->routeIs('password.forced', 'password.forced.store', 'logout')) {
            // A fetch()/AJAX caller (e.g. the assistant widget) follows a redirect
            // silently and ends up trying to JSON-parse the forced-password HTML page,
            // which fails opaquely. Content-negotiate instead of always redirecting.
            if ($request->expectsJson()) {
                return response()->json(['error' => __('agencyos.password.must_change_first')], 409);
            }

            return redirect()->route('password.forced');
        }

        return $next($request);
    }
}
