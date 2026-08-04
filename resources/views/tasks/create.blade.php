@extends('layouts.app')
@section('title', __('agencyos.tasks.create.title'))
@section('page', 'tasks')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.tasks.create.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.tasks.create.subtitle') }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('tasks.store') }}" class="card form-card">
        @csrf
        <div class="form-section">
            <div class="form-section-head"><span class="n">1</span><h2>{{ __('agencyos.tasks.fields.title') }}</h2></div>
            <div class="form-grid">
                <div class="field span2 @error('title') bad @enderror">
                    <label class="req">{{ __('agencyos.tasks.fields.title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required>
                    @error('title')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2 @error('brief') bad @enderror">
                    <label class="req">{{ __('agencyos.tasks.fields.brief') }}</label>
                    <textarea name="brief" required>{{ old('brief') }}</textarea>
                    @error('brief')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2">
                    <label>{{ __('agencyos.tasks.fields.notes') }}</label>
                    <textarea name="notes">{{ old('notes') }}</textarea>
                </div>
                <div class="field">
                    <label>{{ __('agencyos.tasks.fields.priority') }}</label>
                    <select name="priority">
                        @foreach(\App\Enums\Priority::cases() as $priority)
                            <option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ \App\Support\TaskPresenter::priorityTag($priority)['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field @error('first_department_id') bad @enderror">
                    <label class="req">{{ __('agencyos.tasks.fields.first_department') }}</label>
                    <select name="first_department_id" required>
                        <option value="">—</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) old('first_department_id') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    @error('first_department_id')<div class="err">{{ $message }}</div>@enderror
                </div>
                @if($projects->isNotEmpty())
                    <div class="field span2">
                        <label>{{ __('agencyos.tasks.fields.project') }}</label>
                        <select name="project_id">
                            <option value="">{{ __('agencyos.tasks.fields.project_none') }}</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>

        <div class="form-section">
            <div class="form-section-head"><span class="n">2</span><h2>{{ __('agencyos.tasks.fields.reference_links') }}</h2></div>
            <div class="hint" style="margin:-6px 0 12px">{{ __('agencyos.tasks.fields.reference_links_hint') }}</div>
            @error('reference_links')<div class="alert alert-danger" style="margin-bottom:12px"><div>{{ $message }}</div></div>@enderror
            <div class="form-grid">
                @for($i = 0; $i < 3; $i++)
                    <div class="field">
                        <label @class(['req' => $i === 0])>{{ __('agencyos.tasks.fields.reference_link_n', ['n' => $i + 1]) }} — {{ __('agencyos.tasks.fields.url') }}</label>
                        <input type="url" name="reference_links[{{ $i }}][url]" value="{{ old("reference_links.$i.url") }}" placeholder="https://">
                    </div>
                    <div class="field">
                        <label>{{ __('agencyos.tasks.fields.label') }}</label>
                        <input type="text" name="reference_links[{{ $i }}][label]" value="{{ old("reference_links.$i.label") }}" maxlength="255">
                    </div>
                @endfor
            </div>
        </div>

        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('tasks.index') }}">{{ __('agencyos.tasks.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.tasks.create.submit') }}</button>
        </div>
    </form>
</main>
@endsection
