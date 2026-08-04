<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * BRD §18.1 — the verify link is deliberately unauthenticated: the email is opened on
 * whatever device the recipient has at hand, not necessarily one signed in as them. The
 * token itself, not the session, is what proves the click is legitimate.
 */
class EmailVerificationController extends Controller
{
    public function __construct(private readonly EmailVerificationService $verification) {}

    public function verify(Request $request, User $user, string $token): RedirectResponse
    {
        try {
            $this->verification->consume($user, $token);
        } catch (ValidationException $e) {
            return redirect()->route('login')->withErrors($e->errors());
        }

        return redirect()
            ->route($request->user()?->is($user) ? 'dashboard' : 'login')
            ->with('status', __('agencyos.email_verification.verified_flash'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        $email = $user->pending_email ?? ($user->email_verified_at === null ? $user->personal_email : null);

        if ($email === null) {
            return redirect()->back()->withErrors(['email' => __('agencyos.email_verification.already_verified')]);
        }

        $this->verification->issueFor($user, $email);

        return redirect()->back()->with('status', __('agencyos.email_verification.resent_flash'));
    }
}
