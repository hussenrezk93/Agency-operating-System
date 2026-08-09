@extends('layouts.app')
@section('title', __('agencyos.users.create.title'))
@section('page', 'users')
@section('page_header')
    <div class="page-head"><div><h1>{{ __('agencyos.users.create.title') }}</h1></div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
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
                    @php($selectedRole = old('role', 'manager'))
                    @php($roleNeedsDepartment = in_array($selectedRole, ['tl', 'employee'], true))
                    <div class="field @error('role') bad @enderror">
                        <label class="req">{{ __('agencyos.users.fields.role') }}</label>
                        <x-form-select name="role" required onchange="agencyosToggleUserDepartmentField(this.value)"
                            :options="['manager' => __('agencyos.roles.manager'), 'tl' => __('agencyos.roles.tl'), 'employee' => __('agencyos.roles.employee')]"
                            :selected="$selectedRole"/>
                        @error('role')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field @error('department_id') bad @enderror" id="user-department-field" @style(['display:none' => ! $roleNeedsDepartment])>
                        <label class="req">{{ __('agencyos.users.fields.department') }}</label>
                        <x-form-select name="department_id" :required="$roleNeedsDepartment"
                            placeholder="—" :options="$departments->pluck('name', 'id')"
                            :selected="old('department_id')"/>
                        @error('department_id')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <script>
                        function agencyosToggleUserDepartmentField(role) {
                            var field = document.getElementById('user-department-field');
                            var select = field.querySelector('select[name="department_id"]');
                            var needsDepartment = role === 'tl' || role === 'employee';
                            field.style.display = needsDepartment ? '' : 'none';
                            select.required = needsDepartment;
                            if (!needsDepartment) {
                                select.value = '';
                                select.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        }
                    </script>
                @else
                    <div class="field @error('role') bad @enderror">
                        <label class="req">{{ __('agencyos.users.fields.role') }}</label>
                        <x-form-select name="role" required
                            :options="['tl' => __('agencyos.roles.tl'), 'employee' => __('agencyos.roles.employee')]"
                            :selected="old('role', 'employee')"/>
                        @error('role')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <div class="field @error('department_id') bad @enderror">
                        <label class="req">{{ __('agencyos.users.fields.department') }}</label>
                        <x-form-select name="department_id" required
                            placeholder="—" :options="$departments->pluck('name', 'id')"
                            :selected="old('department_id')"/>
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
