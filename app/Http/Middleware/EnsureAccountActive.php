<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A deactivated account is cut off immediately, even with a live session.
 * Only 'inactive' blocks access today — 'on_leave' behavior is an OPEN decision (Q14).
 */
class EnsureAccountActive
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSignInBlocked()) {
            $this->auth->logout($request);

            if ($request->expectsJson()) {
                return response()->json(['error' => __('agencyos.auth.disabled')], 403);
            }

            return redirect()->route('login')
                ->withErrors(['username' => __('agencyos.auth.disabled')]);
        }

        return $next($request);
    }
}
