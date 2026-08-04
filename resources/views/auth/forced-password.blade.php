@extends('layouts.auth')
@section('title', __('agencyos.password.title'))
@section('content')
    <h1 class="auth-title">{{ __('agencyos.password.title') }}</h1>
    <p class="muted small auth-sub">{{ __('agencyos.password.subtitle') }}</p>

    @if ($errors->any())
        <div class="alert alert-danger auth-errors" role="alert"><div>{{ $errors->first() }}</div></div>
    @endif

    <form method="POST" action="{{ route('password.forced.store') }}" novalidate>
        @csrf
        @foreach ([
            ['id'=>'current_password','label'=>__('agencyos.password.current'),'autocomplete'=>'current-password'],
            ['id'=>'password','label'=>__('agencyos.password.new'),'autocomplete'=>'new-password'],
            ['id'=>'password_confirmation','label'=>__('agencyos.password.confirm'),'autocomplete'=>'new-password'],
        ] as $field)
            <div class="field">
                <label for="{{ $field['id'] }}">{{ $field['label'] }}</label>
                <div class="password-wrap">
                    <input class="input" id="{{ $field['id'] }}" name="{{ $field['id'] }}" type="password" autocomplete="{{ $field['autocomplete'] }}" required>
                    <button type="button" class="password-toggle" data-password-toggle="{{ $field['id'] }}" data-show-label="{{ __('agencyos.auth.show_password') }}" data-hide-label="{{ __('agencyos.auth.hide_password') }}">{{ __('agencyos.auth.show_password') }}</button>
                </div>
            </div>
        @endforeach
        <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center">{{ __('agencyos.common.save') }}</button>
    </form>
@endsection
