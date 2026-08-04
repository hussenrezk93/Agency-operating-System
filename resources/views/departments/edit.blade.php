@extends('layouts.app')
@section('title', __('agencyos.departments.edit.title'))
@section('page', 'departments')
@section('content')
<main class="page">
    <div class="page-head"><div><h1>{{ __('agencyos.departments.edit.title') }}</h1></div></div>

    <form method="POST" action="{{ route('departments.update', $department) }}" class="card form-card">
        @csrf
        @method('PATCH')
        <div class="form-section">
            <div class="form-grid">
                <div class="field @error('name') bad @enderror">
                    <label class="req">{{ __('agencyos.departments.fields.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $department->name) }}" maxlength="255" required>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('departments.index') }}">{{ __('agencyos.departments.edit.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.departments.edit.submit') }}</button>
        </div>
    </form>
</main>
@endsection
