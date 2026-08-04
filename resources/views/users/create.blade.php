@extends('layouts.app')
@section('title', __('agencyos.users.create.title'))
@section('page', 'users')
@section('content')
<main class="page">
    <div class="page-head"><div><h1>{{ __('agencyos.users.create.title') }}</h1></div></div>

    <form method="POST" action="{{ route('users.store') }}" class="card form-card">
        @csrf
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('full_name') bad @enderror">
                    <label class="req">{{ __('agencyos.users.fields.full_name') }}</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" maxlength="255" required>
                    @error('full_name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('username') bad @enderror">
                    <label class="req">{{ __('agencyos.users.fields.username') }}</label>
                    <input type="text" name="username" value="{{ old('username') }}" maxlength="255" required>
                    @error('username')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2 @error('personal_email') bad @enderror">
                    <label class="req">{{ __('agencyos.users.fields.personal_email') }}</label>
                    <input type="email" name="personal_email" value="{{ old('personal_email') }}" maxlength="255" required>
                    @error('personal_email')<div class="err">{{ $message }}</div>@enderror
                </div>

                @if($isAdmin)
                    <input type="hidden" name="role" value="manager">
                    <div class="field span2">
                        <label>{{ __('agencyos.users.fields.role') }}</label>
                        <input type="text" value="{{ __('agencyos.roles.manager') }}" disabled>
                    </div>
                @else
                    <div class="field @error('role') bad @enderror">
                        <label class="req">{{ __('agencyos.users.fields.role') }}</label>
                        <select name="role" required>
                            <option value="tl" @selected(old('role') === 'tl')>{{ __('agencyos.roles.tl') }}</option>
                            <option value="employee" @selected(old('role', 'employee') === 'employee')>{{ __('agencyos.roles.employee') }}</option>
                        </select>
                        @error('role')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field @error('department_id') bad @enderror">
                        <label class="req">{{ __('agencyos.users.fields.department') }}</label>
                        <select name="department_id" required>
                            <option value="">—</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="err">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
            <p class="small muted" style="margin-top:6px">{{ __('agencyos.users.create.temp_password_note') }}</p>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('users.index') }}">{{ __('agencyos.users.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.users.create.submit') }}</button>
        </div>
    </form>
</main>
@endsection
