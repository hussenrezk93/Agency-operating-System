@extends('layouts.auth')
@section('title', __('agencyos.password_reset.forgot_title'))
@section('content')
    <h1 class="auth-title">{{ __('agencyos.password_reset.forgot_title') }}</h1>
    <p class="muted small auth-sub">{{ __('agencyos.password_reset.forgot_subtitle') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger auth-errors" role="alert">
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.forgot.store') }}" novalidate>
        @csrf
        <div class="auth-field">
            <x-icon name="user" class="ic"/>
            <input id="username" name="username" value="{{ old('username') }}" placeholder="{{ __('agencyos.auth.username') }}" aria-label="{{ __('agencyos.auth.username') }}" autocomplete="username" autofocus required>
        </div>
        <button class="btn btn-primary auth-submit" type="submit">{{ __('agencyos.password_reset.forgot_submit') }}</button>
    </form>

    <p class="muted small auth-sub"><a href="{{ route('login') }}">{{ __('agencyos.password_reset.back_to_login') }}</a></p>
@endsection
