@extends('layouts.app')
@section('title', __('agencyos.temporary_leadership.create.title'))
@section('page', 'temporary-tl')
@section('page_header')
    <div class="page-head"><div><h1>{{ __('agencyos.temporary_leadership.create.title') }}</h1></div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <form method="POST" action="{{ route('temporary-leadership.store') }}" class="card form-card">
        @csrf
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('department_id') bad @enderror">
                    <label class="req">{{ __('agencyos.temporary_leadership.fields.department') }}</label>
                    <x-form-select name="department_id" required
                        placeholder="—" :options="$departments->pluck('name', 'id')"
                        :selected="old('department_id')"/>
                    @error('department_id')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('user_id') bad @enderror">
                    <label class="req">{{ __('agencyos.temporary_leadership.fields.temp_leader') }}</label>
                    <x-form-select name="user_id" required placeholder="—"
                        :options="$candidates->mapWithKeys(fn ($candidate) => [$candidate->id => $candidate->full_name.' · '.__('agencyos.roles.'.$candidate->role->code)])"
                        :selected="old('user_id')"/>
                    @error('user_id')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('start_date') bad @enderror">
                    <label class="req">{{ __('agencyos.temporary_leadership.fields.start_date') }}</label>
                    <input type="date" name="start_date" value="{{ old('start_date', now()->toDateString()) }}" required>
                    @error('start_date')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('end_date') bad @enderror">
                    <label class="req">{{ __('agencyos.temporary_leadership.fields.end_date') }}</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" required>
                    @error('end_date')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2 @error('reason') bad @enderror">
                    <label class="req">{{ __('agencyos.temporary_leadership.fields.reason') }}</label>
                    <textarea name="reason" required>{{ old('reason') }}</textarea>
                    @error('reason')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="alert alert-info"><div>{{ __('agencyos.temporary_leadership.create.effect_note') }}</div></div>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('temporary-leadership.index') }}">{{ __('agencyos.temporary_leadership.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.temporary_leadership.create.submit') }}</button>
        </div>
    </form>
</main>
@endsection
