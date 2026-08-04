<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => [
                'required',
                'string',
                Rule::in(config('app.supported_locales', ['ar', 'en'])),
            ],
        ]);

        $locale = $validated['locale'];

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        // fallback: a language switch posted without a Referer (or from a page that has
        // since become unreachable) must land on the dashboard, never bounce to login.
        return back(fallback: route('dashboard'))->withCookie(cookie(
            name: 'agencyos_locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            path: '/',
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
