<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForcedPasswordRequest;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ForcedPasswordController extends Controller
{
    public function __construct(private readonly AuthService $auth) {}

    public function show(): View
    {
        return view('auth.forced-password');
    }

    public function store(ForcedPasswordRequest $request): RedirectResponse
    {
        $this->auth->completeForcedPasswordChange(
            $request->user(),
            $request->validated('password'),
            $request,
        );

        return redirect()->route('dashboard')
            ->with('status', __('agencyos.password.saved'));
    }
}
