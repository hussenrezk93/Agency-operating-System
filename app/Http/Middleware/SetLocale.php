<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * A `?set_locale=xx` query param on ANY GET request switches the language for
     * that same request/render — no separate POST-then-redirect round trip. Used
     * by the topbar switcher (a plain link to the current URL); LocaleController's
     * POST /locale endpoint still exists for the logged-out auth pages' switcher.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('app.supported_locales', ['ar', 'en']);
        $requested = $request->query('set_locale');

        if (is_string($requested) && in_array($requested, $supportedLocales, true)) {
            $locale = $requested;
            $request->session()->put('locale', $locale);
            Cookie::queue('agencyos_locale', $locale, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax');
        } else {
            $locale = $request->session()->get(
                'locale',
                $request->cookie('agencyos_locale', config('app.locale', 'ar')),
            );
        }

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
