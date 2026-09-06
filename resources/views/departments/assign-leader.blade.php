@extends('layouts.app')
@section('title', __('agencyos.departments.assign_leader.title'))
@section('page', 'departments')
@section('page_header')
    <div class="page-head"><div><h1>{{ __('agencyos.departments.assign_leader.title', ['name' => $department->name]) }}</h1></div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <form method="POST" action="{{ route('departments.assign-leader', $department) }}" class="dx-card form-card">
        @csrf
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('primary_leader_id') bad @enderror">
                    <label class="req">{{ __('agencyos.departments.fields.primary_leader') }}</label>
                    <x-form-select name="primary_leader_id" required
                        placeholder="—" :options="$leaders->pluck('full_name', 'id')"
                        :selected="old('primary_leader_id')"/>
                    @error('primary_leader_id')<div class="err">{{ $message }}</div>@enderror
                    <div class="hint">{{ __('agencyos.departments.assign_leader.hint') }}</div>
                </div>
            </div>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('departments.index') }}">{{ __('agencyos.departments.assign_leader.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.departments.assign_leader.submit') }}</button>
        </div>
    </form>
</main>
@endsection
