@extends('layouts.app')
@section('title', __('agencyos.projects.create.title'))
@section('page', 'projects')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.projects.create.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.projects.create.subtitle') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('projects.store') }}" class="card form-card">
        @csrf
        <div class="form-section">
            <div class="form-section-head"><span class="n">1</span><h2>{{ __('agencyos.projects.fields.name') }}</h2></div>
            <div class="form-grid">
                <div class="field @error('client_id') bad @enderror">
                    <label class="req">{{ __('agencyos.projects.fields.client') }}</label>
                    <select name="client_id" required>
                        <option value="">—</option>
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </select>
                    @error('client_id')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field @error('name') bad @enderror">
                    <label class="req">{{ __('agencyos.projects.fields.name') }}</label>
                    <input type="text" name="name" value="{{ old('name') }}" maxlength="255" required>
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2">
                    <label>{{ __('agencyos.projects.fields.description') }}</label>
                    <textarea name="description">{{ old('description') }}</textarea>
                </div>
                <div class="field span2 @error('department_ids') bad @enderror">
                    <label class="req">{{ __('agencyos.projects.fields.departments') }}</label>
                    <div style="display:flex;gap:14px;flex-wrap:wrap">
                        @foreach($departments as $department)
                            <label style="display:flex;align-items:center;gap:6px;font-weight:400">
                                <input type="checkbox" name="department_ids[]" value="{{ $department->id }}"
                                    @checked(collect(old('department_ids', []))->contains((string) $department->id))>
                                {{ $department->name }}
                            </label>
                        @endforeach
                    </div>
                    @error('department_ids')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head"><span class="n">2</span><h2>{{ __('agencyos.tasks.fields.reference_links') }}</h2></div>
            <div class="form-grid">
                @for($i = 0; $i < 3; $i++)
                    <div class="field">
                        <label>{{ __('agencyos.tasks.fields.reference_link_n', ['n' => $i + 1]) }} — {{ __('agencyos.tasks.fields.url') }}</label>
                        <input type="url" name="links[{{ $i }}][url]" value="{{ old("links.$i.url") }}" placeholder="https://">
                    </div>
                    <div class="field">
                        <label>{{ __('agencyos.tasks.fields.label') }}</label>
                        <input type="text" name="links[{{ $i }}][label]" value="{{ old("links.$i.label") }}" maxlength="255">
                    </div>
                @endfor
            </div>
        </div>

        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('projects.index') }}">{{ __('agencyos.projects.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.projects.create.submit') }}</button>
        </div>
    </form>
</main>
@endsection
