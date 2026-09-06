@extends('layouts.app')
@section('title', __('agencyos.departments.create.title'))
@section('page', 'departments')
@section('page_header')
    <div class="page-head"><div><h1>{{ __('agencyos.departments.create.title') }}</h1></div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <form method="POST" action="{{ route('departments.store') }}" class="dx-card form-card">
        @csrf
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('name') bad @enderror">
                    <label class="req">{{ __('agencyos.departments.fields.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('primary_leader_id') bad @enderror">
                    <label>{{ __('agencyos.departments.fields.primary_leader') }}</label>
                    <x-form-select name="primary_leader_id"
                        placeholder="—" :options="$leaders->pluck('full_name', 'id')"
                        :selected="old('primary_leader_id')"/>
                    @error('primary_leader_id')<div class="err">{{ $message }}</div>@enderror
                    <div class="hint">{{ __('agencyos.departments.create.leader_hint') }}</div>
                </div>
                <div class="field @error('special_role') bad @enderror">
                    <label>{{ __('agencyos.departments.fields.special_role') }}</label>
                    <x-form-select name="special_role"
                        placeholder="—" :options="collect(\App\Enums\DepartmentSpecialRole::cases())->mapWithKeys(fn ($role) => [$role->value => $role->label()])"
                        :selected="old('special_role')"/>
                    @error('special_role')<div class="err">{{ $message }}</div>@enderror
                    <div class="hint">{{ __('agencyos.departments.fields.special_role_hint') }}</div>
                </div>
            </div>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('departments.index') }}">{{ __('agencyos.departments.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.departments.create.submit') }}</button>
        </div>
    </form>
</main>
@endsection
