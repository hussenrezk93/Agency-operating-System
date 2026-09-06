<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#FC6E20">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    {{-- The actual font FILES (not just the @font-face CSS) come from gstatic, a
         different origin — without preconnecting here too, that connection only opens
         after the googleapis.com stylesheet has already downloaded and been parsed,
         adding a full extra round-trip before the Arabic font can even start fetching. --}}
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    {{-- filemtime cache-busting — see the matching comment in layouts/app.blade.php.
         Without this, the .htaccess far-future cache header means a browser (or any
         proxy in front of it) keeps serving whatever version it first fetched from this
         unchanging URL, forever — a real bug here specifically, since this stylesheet
         gets edited far more often than this rarely-visited layout's own markup. --}}
    <link rel="stylesheet" href="{{ asset('assets/agencyos.css') }}?v={{ filemtime(public_path('assets/agencyos.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/agencyos-laravel.css') }}?v={{ filemtime(public_path('assets/agencyos-laravel.css')) }}">
    <title>{{ __('agencyos.common.system_name') }} — @yield('title')</title>
</head>
<body data-shell="none">
<div class="auth">
    <div class="auth-shell">
        <div class="auth-shell-left" aria-hidden="true">
            <span class="auth-shell-tab">{{ __('agencyos.auth.title') }}</span>
        </div>
        <div class="auth-shell-right">
            <div class="auth-brand">
                <div class="auth-logo"><img src="{{ asset('images/logo.svg') }}" alt="Agency OS"></div>
            </div>
            @yield('content')
            <div class="auth-meta">
                <form class="language-form" method="POST" action="{{ route('locale.update') }}">
                    @csrf
                    <div class="language-switch" data-active="{{ app()->getLocale() }}" role="group" aria-label="{{ __('agencyos.language.switch_label') }}">
                        <button type="submit" name="locale" value="en"><span class="lang-choice lang-en">EN</span></button>
                        <button type="submit" name="locale" value="ar"><span class="lang-choice lang-ar">العربية</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('[data-password-toggle]').forEach(function(button){
    button.addEventListener('click', function(){
        var input=document.getElementById(button.dataset.passwordToggle);
        if(!input) return;
        var show=input.type==='password';
        input.type=show?'text':'password';
        button.classList.toggle('is-visible', show);
        button.setAttribute('aria-label', show?button.dataset.hideLabel:button.dataset.showLabel);
    });
});
</script>
</body>
</html>
