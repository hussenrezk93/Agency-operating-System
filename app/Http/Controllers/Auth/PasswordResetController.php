<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordForgotRequest;
use App\Http\Requests\PasswordResetRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * CR-002 (2026-09) — self-service password reset. The reset link itself is
 * deliberately unauthenticated (routes/web.php), same reasoning as email verification:
 * the token proves the click, not the session.
 */
class PasswordResetController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function requestStore(PasswordForgotRequest $request): RedirectResponse
    {
        $this->auth->requestPasswordReset($request->validated('username'), $request);

        // Identical response whether or not the account exists — requestPasswordReset()
        // never signals which happened, so there is nothing to branch on here.
        return redirect()->route('login')->with('status', __('agencyos.password_reset.generic_sent_flash'));
    }

    public function show(User $user, string $token): View
    {
        return view('auth.reset-password', ['user' => $user, 'token' => $token]);
    }

    public function store(PasswordResetRequest $request, User $user, string $token): RedirectResponse
    {
        try {
            $this->auth->resetPassword($user, $token, $request->validated('password'), $request);
        } catch (ValidationException $e) {
            return redirect()->route('login')->withErrors($e->errors());
        }

        return redirect()->route('login')->with('status', __('agencyos.password_reset.reset_success_flash'));
    }
}
