@extends('layouts.auth')
@section('title', __('agencyos.auth.title'))
@section('content')
    <h1 class="auth-title">{{ __('agencyos.auth.title') }}</h1>
    <p class="muted small auth-sub">{{ __('agencyos.auth.subtitle') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger auth-errors" role="alert">
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <div class="field">
            <label for="username">{{ __('agencyos.auth.username') }}</label>
            <input class="input" id="username" name="username" value="{{ old('username') }}" autocomplete="username" autofocus required>
        </div>
        <div class="field">
            <label for="password">{{ __('agencyos.auth.password') }}</label>
            <div class="password-wrap">
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="button" class="password-toggle" data-password-toggle="password" data-show-label="{{ __('agencyos.auth.show_password') }}" data-hide-label="{{ __('agencyos.auth.hide_password') }}">{{ __('agencyos.auth.show_password') }}</button>
            </div>
        </div>
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;margin-top:6px">{{ __('agencyos.auth.submit') }}</button>
    </form>
    <div class="divider"></div>
    <p class="small muted" style="text-align:center;margin:0">{{ __('agencyos.auth.account_note') }}</p>
@endsection
