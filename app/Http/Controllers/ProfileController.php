<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateOwnEmailRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BRD §18.1 — every role edits their own personal email here (not through
 * UserController, which is Admin/Manager acting on someone ELSE's account).
 * The self-edit still goes through UserService::updateProfile()'s same
 * pending-email + verification flow, so it is audited identically either way.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(UpdateOwnEmailRequest $request): JsonResponse|RedirectResponse
    {
        $actor = $request->user();

        $updated = $this->service->updateProfile(
            $actor,
            ['personal_email' => $request->string('personal_email')->toString()],
            $actor,
        );

        if (! $request->expectsJson()) {
            return redirect()->route('profile.edit')->with('status', __('agencyos.profile.flash.email_updated'));
        }

        return response()->json(['data' => $updated]);
    }
}
