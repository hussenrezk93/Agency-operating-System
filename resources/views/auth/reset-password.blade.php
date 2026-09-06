@extends('layouts.auth')
@section('title', __('agencyos.password_reset.reset_title'))
@section('content')
    <h1 class="auth-title">{{ __('agencyos.password_reset.reset_title') }}</h1>
    <p class="muted small auth-sub">{{ __('agencyos.password_reset.reset_subtitle') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger auth-errors" role="alert">
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.reset.store', [$user, $token]) }}" novalidate>
        @csrf
        @foreach ([
            ['id' => 'password', 'label' => __('agencyos.password_reset.reset_password_label'), 'autocomplete' => 'new-password'],
            ['id' => 'password_confirmation', 'label' => __('agencyos.password_reset.reset_confirm_label'), 'autocomplete' => 'new-password'],
        ] as $field)
            <div class="auth-field">
                <x-icon name="lock" class="ic"/>
                <input id="{{ $field['id'] }}" name="{{ $field['id'] }}" type="password" placeholder="{{ $field['label'] }}" aria-label="{{ $field['label'] }}" autocomplete="{{ $field['autocomplete'] }}" required>
                <button type="button" class="password-toggle" data-password-toggle="{{ $field['id'] }}" data-show-label="{{ __('agencyos.auth.show_password') }}" data-hide-label="{{ __('agencyos.auth.hide_password') }}" aria-label="{{ __('agencyos.auth.show_password') }}">
                    <x-icon name="eye" class="ic-show"/>
                    <x-icon name="eye-off" class="ic-hide"/>
                </button>
            </div>
        @endforeach
        <button class="btn btn-primary auth-submit" type="submit">{{ __('agencyos.password_reset.reset_submit') }}</button>
    </form>
@endsection
