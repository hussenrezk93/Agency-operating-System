@extends('layouts.app')
@section('title', __('agencyos.departments.create.title'))
@section('page', 'departments')
@section('content')
<main class="page">
    <div class="page-head"><div><h1>{{ __('agencyos.departments.create.title') }}</h1></div></div>

    <form method="POST" action="{{ route('departments.store') }}" class="card form-card">
        @csrf
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('name') bad @enderror">
                    <label class="req">{{ __('agencyos.departments.fields.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('primary_leader_id') bad @enderror">
                    <label class="req">{{ __('agencyos.departments.fields.primary_leader') }}</label>
                    <select name="primary_leader_id" required>
                        <option value="">—</option>
                        @foreach($leaders as $leader)
                            <option value="{{ $leader->id }}" @selected((string) old('primary_leader_id') === (string) $leader->id)>{{ $leader->full_name }}</option>
                        @endforeach
                    </select>
                    @error('primary_leader_id')<div class="err">{{ $message }}</div>@enderror
                    <div class="hint">{{ __('agencyos.departments.create.leader_hint') }}</div>
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
