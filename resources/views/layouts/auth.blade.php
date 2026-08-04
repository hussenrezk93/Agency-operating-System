<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#F97316">
    <link rel="icon" type="image/png" href="{{ asset('images/agencyos-favicon-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/agencyos.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/agencyos-laravel.css') }}">
    <title>{{ __('agencyos.common.system_name') }} — @yield('title')</title>
</head>
<body data-shell="none">
<div class="auth">
    <div class="auth-card card">
        <div class="auth-brand">
            <div class="auth-logo"><img src="{{ asset('images/agencyos-company-logo-white.png') }}" alt="Agency OS"></div>
            <div class="brand-name" style="font-size:22px">Agency<b>OS</b></div>
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
<script>
document.querySelectorAll('[data-password-toggle]').forEach(function(button){
    button.addEventListener('click', function(){
        var input=document.getElementById(button.dataset.passwordToggle);
        if(!input) return;
        var show=input.type==='password';
        input.type=show?'text':'password';
        button.textContent=show?button.dataset.hideLabel:button.dataset.showLabel;
    });
});
</script>
</body>
</html>
