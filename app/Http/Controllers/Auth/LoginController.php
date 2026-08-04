<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $result = $this->auth->attempt(
            $request->validated('username'),
            $request->validated('password'),
            $request,
            $request->throttleKey(),
        );

        return match ($result) {
            'ok' => redirect()->intended(route('dashboard')),
            'throttled' => back()
                ->withErrors(['username' => __('agencyos.auth.throttled')])
                ->onlyInput('username'),
            'disabled' => back()
                ->withErrors(['username' => __('agencyos.auth.disabled')])
                ->onlyInput('username'),
            default => back()
                ->withErrors(['username' => __('agencyos.auth.invalid')]) // generic on purpose
                ->onlyInput('username'),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->logout($request);

        return redirect()->route('login');
    }
}
