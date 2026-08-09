@extends('layouts.app')
@section('title', __('agencyos.users.edit.title'))
@section('page', 'users')
@section('page_header')
    <div class="page-head"><div><h1>{{ __('agencyos.users.edit.title') }}</h1><div class="page-sub">{{ $user->full_name }} &middot; @{{ $user->username }}</div></div></div>
    <x-topbar-controls/>
@endsection
@section('content')
@php($showDepartment = in_array($user->role->code, ['tl', 'employee'], true))
<main class="page">
    <form method="POST" action="{{ route('users.update', $user) }}" class="card form-card">
        @csrf
        @method('PATCH')
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('full_name') bad @enderror">
                    <label class="req">{{ __('agencyos.users.fields.full_name') }}</label>
                    <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" maxlength="255" required>
                    @error('full_name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('personal_email') bad @enderror">
                    <label class="req">{{ __('agencyos.users.fields.personal_email') }}</label>
                    <input type="email" name="personal_email" value="{{ old('personal_email', $user->personal_email) }}" maxlength="255" required>
                    @error('personal_email')<div class="err">{{ $message }}</div>@enderror
                </div>
                @if($showDepartment)
                    <div class="field span2 @error('department_id') bad @enderror">
                        <label class="req">{{ __('agencyos.users.fields.department') }}</label>
                        <x-form-select name="department_id" required
                            :options="$departments->pluck('name', 'id')"
                            :selected="old('department_id', $user->department_id)"/>
                        @error('department_id')<div class="err">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('users.index') }}">{{ __('agencyos.users.edit.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.users.edit.submit') }}</button>
        </div>
    </form>
</main>
@endsection
