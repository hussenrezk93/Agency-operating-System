@extends('layouts.app')
@section('title', __('agencyos.tasks.edit.title'))
@section('page', 'tasks')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.tasks.edit.title') }}</h1>
            <div class="page-sub">{{ $task->task_code }} — {{ __('agencyos.tasks.edit.subtitle') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <form method="POST" action="{{ route('tasks.update', $task) }}" class="card form-card">
        @csrf
        @method('PATCH')
        <div class="form-section">
            <div class="form-grid">
                <div class="field span2 @error('title') bad @enderror">
                    <label class="req">{{ __('agencyos.tasks.fields.title') }}</label>
                    <input type="text" name="title" value="{{ old('title', $task->title) }}" maxlength="255" required>
                    @error('title')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2 @error('brief') bad @enderror">
                    <label class="req">{{ __('agencyos.tasks.fields.brief') }}</label>
                    <textarea name="brief" required>{{ old('brief', $task->brief) }}</textarea>
                    @error('brief')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="field span2">
                    <label>{{ __('agencyos.tasks.fields.notes') }}</label>
                    <textarea name="notes">{{ old('notes', $task->notes) }}</textarea>
                </div>
                <div class="field">
                    <label>{{ __('agencyos.tasks.fields.priority') }}</label>
                    <x-form-select name="priority"
                        :options="collect(\App\Enums\Priority::cases())->mapWithKeys(fn ($p) => [$p->value => \App\Support\TaskPresenter::priorityTag($p)['label']])"
                        :selected="old('priority', $task->priority->value)"/>
                </div>
            </div>
        </div>

        <div class="form-actions-sticky">
            <a class="btn btn-outline" href="{{ route('tasks.show', $task) }}">{{ __('agencyos.tasks.create.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('agencyos.tasks.edit.submit') }}</button>
        </div>
    </form>
</main>
@endsection
