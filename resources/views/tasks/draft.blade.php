@extends('layouts.app')
@section('title', __('agencyos.tasks.draft.title'))
@section('page', 'tasks')
@section('page_header')
    <div class="page-head">
        <div>
            <div class="small muted mono">{{ $task->task_code }}</div>
            <h1 style="margin:2px 0 6px">{{ $task->title }}</h1>
            <span class="badge b-neutral"><span class="bdot"></span>{{ __('agencyos.tasks.draft.badge') }}</span>
        </div>
        <div class="page-actions">
            @if($canPublish || $canDelete)
                <a class="btn btn-outline btn-sm" href="{{ route('tasks.edit-form', $task) }}">{{ __('agencyos.tasks.show.edit') }}</a>
            @endif
            <a class="small" href="{{ route('tasks.index') }}">{{ __('agencyos.tasks.show.back_to_tasks') }}</a>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="alert alert-brand" style="margin-bottom:18px"><div>{{ __('agencyos.tasks.draft.notice') }}</div></div>

    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>{{ __('agencyos.tasks.show.details') }}</h2></div>
        <div class="card-body">
            <div class="kv-row"><span>{{ __('agencyos.tasks.fields.brief') }}</span><b>{{ $task->brief }}</b></div>
            @if($task->notes)
                <div class="kv-row"><span>{{ __('agencyos.tasks.fields.notes') }}</span><b>{{ $task->notes }}</b></div>
            @endif
            <div class="kv-row"><span>{{ __('agencyos.tasks.fields.priority') }}</span><b>{{ \App\Support\TaskPresenter::priorityTag($task->priority)['label'] }}</b></div>
            @if($task->project)
                <div class="kv-row"><span>{{ __('agencyos.tasks.fields.project') }}</span><b>{{ $task->project->name }}</b></div>
            @endif
        </div>
    </div>

    @if($canPublish)
        <div class="card" style="margin-bottom:18px">
            <div class="card-head"><h2>{{ __('agencyos.tasks.draft.publish_title') }}</h2></div>
            <div class="card-body">
                <form method="POST" action="{{ route('tasks.publish', $task) }}">
                    @csrf
                    <div class="field @error('first_department_id') bad @enderror">
                        <label class="req">{{ __('agencyos.tasks.fields.first_department') }}</label>
                        <x-form-select name="first_department_id" required
                            placeholder="—" :options="$departments->pluck('name', 'id')"
                            :selected="old('first_department_id')"/>
                        @error('first_department_id')<div class="err">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.draft.publish_button') }}</button>
                </form>
            </div>
        </div>
    @endif

    @if($canDelete)
        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('{{ __('agencyos.tasks.draft.delete_confirm') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger-outline btn-sm">{{ __('agencyos.tasks.draft.delete_button') }}</button>
        </form>
    @endif
</main>
@endsection
