@extends('layouts.auth')
@section('title', __('agencyos.auth.title'))
@section('content')
    <h1 class="auth-title">{{ __('agencyos.auth.title') }}</h1>
    <p class="muted small auth-sub">{{ __('agencyos.auth.subtitle') }}</p>

    @if (session('status'))
        <div class="alert alert-success auth-errors" role="status">
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger auth-errors" role="alert">
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="auth-field">
            <x-icon name="user" class="ic"/>
            <input id="username" name="username" value="{{ old('username') }}" placeholder="{{ __('agencyos.auth.username') }}" aria-label="{{ __('agencyos.auth.username') }}" autocomplete="username" autofocus required>
        </div>
        <div class="auth-field">
            <x-icon name="lock" class="ic"/>
            <input id="password" name="password" type="password" placeholder="{{ __('agencyos.auth.password') }}" aria-label="{{ __('agencyos.auth.password') }}" autocomplete="current-password" required>
            <button type="button" class="password-toggle" data-password-toggle="password" data-show-label="{{ __('agencyos.auth.show_password') }}" data-hide-label="{{ __('agencyos.auth.hide_password') }}" aria-label="{{ __('agencyos.auth.show_password') }}">
                <x-icon name="eye" class="ic-show"/>
                <x-icon name="eye-off" class="ic-hide"/>
            </button>
        </div>
        <button class="btn btn-primary auth-submit" type="submit">{{ __('agencyos.auth.submit') }}</button>
    </form>

    <p class="muted small auth-sub"><a href="{{ route('password.forgot') }}">{{ __('agencyos.auth.forgot_password_link') }}</a></p>
@endsection
