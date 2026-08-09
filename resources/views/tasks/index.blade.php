<?php $isMyTasks = request('view') === 'my'; ?>
@extends('layouts.app')
@section('title', __($isMyTasks ? 'agencyos.tasks.index.title_my' : 'agencyos.tasks.index.title'))
@section('page', 'tasks')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __($isMyTasks ? 'agencyos.tasks.index.title_my' : 'agencyos.tasks.index.title') }}</h1>
            <div class="page-sub">{{ __($isMyTasks ? 'agencyos.tasks.index.subtitle_my' : 'agencyos.tasks.index.subtitle') }}</div>
        </div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('tasks.create-form') }}"><x-icon name="plus"/> {{ __('agencyos.tasks.index.new_task') }}</a>
            </div>
        @endif
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<?php $actor = auth()->user(); ?>
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    @if($actor->roleCode()->value === 'tl')
        <div class="card glass-dark" style="padding:10px 14px;margin-bottom:14px;display:flex;gap:8px">
            <a class="btn btn-sm {{ request('view') === 'my' ? 'btn-outline' : 'btn-primary' }}" href="{{ route('tasks.index') }}">{{ __('agencyos.tasks.nav.all') }}</a>
            <a class="btn btn-sm {{ request('view') === 'my' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('tasks.index', ['view' => 'my']) }}">{{ __('agencyos.tasks.nav.my') }}</a>
        </div>
    @endif

    @php
        $statusOptions = collect(\App\Enums\WorkflowStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => \App\Support\TaskPresenter::workflowBadge($status)['label']]);
        $priorityOptions = collect(\App\Enums\Priority::cases())
            ->mapWithKeys(fn ($priority) => [$priority->value => \App\Support\TaskPresenter::priorityTag($priority)['label']]);
        $departmentOptions = ($actor->roleCode()->value === 'manager')
            ? collect($departments)->mapWithKeys(fn ($department) => [(string) $department->id => $department->name])
            : collect();
    @endphp
    <div class="card glass-dark" style="padding:12px 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <x-filter-select name="status" :options="$statusOptions" :selected="request('status')" :placeholder="__('agencyos.tasks.index.filter_status')"/>
        <x-filter-select name="priority" :options="$priorityOptions" :selected="request('priority')" :placeholder="__('agencyos.tasks.index.filter_priority')"/>
        @if($actor->roleCode()->value === 'manager')
            <x-filter-select name="department_id" :options="$departmentOptions" :selected="request('department_id')" :placeholder="__('agencyos.tasks.index.filter_department')"/>
        @endif
        @if(request()->hasAny(['status', 'priority', 'department_id']))
            <a class="btn btn-sm btn-outline" href="{{ route('tasks.index', request('view') ? ['view' => request('view')] : []) }}"><x-icon name="x"/> {{ __('agencyos.tasks.index.clear') }}</a>
        @endif
    </div>

    <div class="card glass-dark">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('agencyos.tasks.index.column_title') }}</th>
                    <th>{{ __('agencyos.tasks.index.column_route') }}</th>
                    <th>{{ __('agencyos.tasks.index.column_assignee') }}</th>
                    <th>{{ __('agencyos.tasks.index.column_status') }}</th>
                    <th>{{ __('agencyos.tasks.index.column_due') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($tasks as $task)
                    @php($step = $task->currentStep)
                    <tr>
                        <td>
                            <a href="{{ route('tasks.show', $task) }}" style="font-weight:700;color:inherit">{{ $task->title }}</a>
                            <div class="small muted mono">{{ $task->task_code }}@if($task->project) &middot; {{ $task->project->name }}@endif</div>
                        </td>
                        <td>{{ $step?->department?->name ?? '—' }}</td>
                        <td>{{ $step?->activeAssignment?->assignee?->full_name ?? '—' }}</td>
                        <td>
                            @if($task->isOnHold())
                                @php($badge = \App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status))
                                <span class="badge {{ $badge['class'] }}"><span class="bdot"></span>{{ $badge['label'] }}</span>
                            @elseif($step)
                                @php($badge = \App\Support\TaskPresenter::workflowBadge($step->workflow_status))
                                <span class="badge {{ $badge['class'] }}"><span class="bdot"></span>{{ $badge['label'] }}</span>
                            @else
                                @php($badge = \App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status))
                                <span class="badge {{ $badge['class'] }}"><span class="bdot"></span>{{ $badge['label'] }}</span>
                            @endif
                        </td>
                        <td class="mono small">
                            {{ $step?->current_due_at?->format('Y-m-d') ?? '—' }}
                            @if($step && ! $task->isOnHold() && in_array($step->deadline_status->value, ['due_soon', 'overdue'], true))
                                @php($deadlineBadge = \App\Support\TaskPresenter::deadlineBadge($step->deadline_status))
                                <span class="badge {{ $deadlineBadge['class'] }}" style="margin-inline-start:6px">{{ $deadlineBadge['label'] }}</span>
                            @endif
                        </td>
                        <td style="text-align:end"><a class="btn btn-sm btn-outline" href="{{ route('tasks.show', $task) }}"><x-icon name="eye"/> {{ __('agencyos.tasks.index.open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.tasks.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
