@extends('layouts.app')
@section('title', __('agencyos.profile.title'))
@section('page', 'profile')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.profile.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.profile.subtitle') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    <form method="POST" action="{{ route('profile.update') }}" class="card form-card">
        @csrf
        @method('PATCH')
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('personal_email') bad @enderror">
                    <label class="req">{{ __('agencyos.profile.current_email') }}</label>
                    <input type="email" name="personal_email" value="{{ old('personal_email', $user->personal_email) }}" maxlength="255" required>
                    @error('personal_email')<div class="err">{{ $message }}</div>@enderror
                    @if($user->pending_email)
                        <div class="small muted" style="margin-top:6px">{{ __('agencyos.profile.pending_email', ['email' => $user->pending_email]) }}</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="form-actions-sticky">
            <button type="submit" class="btn btn-primary">{{ __('agencyos.profile.submit') }}</button>
        </div>
    </form>
</main>
@endsection
