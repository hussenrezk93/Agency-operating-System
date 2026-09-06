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
        <div class="dx-card" style="padding:10px 14px;margin-bottom:14px;display:flex;flex-direction:row;gap:8px">
            <a class="btn btn-sm {{ request('view') === 'my' ? 'btn-outline' : 'btn-primary' }}" href="{{ route('tasks.index') }}">{{ __('agencyos.tasks.nav.all') }}</a>
            <a class="btn btn-sm {{ request('view') === 'my' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('tasks.index', ['view' => 'my']) }}">{{ __('agencyos.tasks.nav.my') }}</a>
        </div>
    @endif

    @php
        // "Awaiting my review" is a question about the VIEWER, not a stored status, so it
        // is not a WorkflowStatus case — it heads the list because it is what a reviewer
        // opens this page for. Employees have no review turn, so they never see it.
        $statusOptions = collect(\App\Enums\WorkflowStatus::cases())
            ->mapWithKeys(fn ($status) => [$status->value => \App\Support\TaskPresenter::workflowBadge($status)['label']]);

        if (in_array($actor->roleCode()->value, ['manager', 'tl'], true)) {
            $statusOptions = collect([
                \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW => __('agencyos.tasks.index.filter_awaiting_me'),
            ])->union($statusOptions);
        }
        $priorityOptions = collect(\App\Enums\Priority::cases())
            ->mapWithKeys(fn ($priority) => [$priority->value => \App\Support\TaskPresenter::priorityTag($priority)['label']]);
        $departmentOptions = ($actor->roleCode()->value === 'manager')
            ? collect($departments)->mapWithKeys(fn ($department) => [(string) $department->id => $department->name])
            : collect();
    @endphp
    <div class="dx-card" style="padding:12px 14px;display:flex;flex-direction:row;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        <x-filter-select name="status" :options="$statusOptions" :selected="request('status')" :placeholder="__('agencyos.tasks.index.filter_status')"/>
        <x-filter-select name="priority" :options="$priorityOptions" :selected="request('priority')" :placeholder="__('agencyos.tasks.index.filter_priority')"/>
        @if($actor->roleCode()->value === 'manager')
            <x-filter-select name="department_id" :options="$departmentOptions" :selected="request('department_id')" :placeholder="__('agencyos.tasks.index.filter_department')"/>
        @endif
        @if(request()->hasAny(['status', 'priority', 'department_id']))
            <a class="btn btn-sm btn-outline" href="{{ route('tasks.index', request('view') ? ['view' => request('view')] : []) }}"><x-icon name="x"/> {{ __('agencyos.tasks.index.clear') }}</a>
        @endif
    </div>

    <div class="dx-card">
        <div class="dx-table-wrap">
            <table class="dx-table">
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
                            <a class="dx-td-main" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                            <span class="dx-td-sub">{{ $task->task_code }}@if($task->project) &middot; {{ $task->project->name }}@endif</span>
                        </td>
                        <td>{{ $step?->department?->name ?? '—' }}</td>
                        <td>{{ $step?->activeAssignment?->assignee?->full_name ?? '—' }}</td>
                        <td>
                            @if($task->isOnHold())
                                <x-dx-pill :badge="\App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status)"/>
                            @elseif($step)
                                <x-dx-pill :badge="\App\Support\TaskPresenter::workflowBadge($step->workflow_status, $step->activeAssignment?->is_self_assigned)"/>
                            @else
                                <x-dx-pill :badge="\App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status)"/>
                            @endif
                        </td>
                        <td class="dx-td-num">
                            {{ $step?->current_due_at?->format('Y-m-d') ?? '—' }}
                            @if($step && ! $task->isOnHold() && in_array($step->deadline_status->value, ['due_soon', 'overdue'], true))
                                <x-dx-pill :badge="\App\Support\TaskPresenter::deadlineBadge($step->deadline_status)" style="margin-inline-start:6px"/>
                            @endif
                        </td>
                        <td class="dx-td-end"><a class="btn btn-sm btn-outline" href="{{ route('tasks.show', $task) }}"><x-icon name="eye"/> {{ __('agencyos.tasks.index.open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="dx-empty-cell">{{ __('agencyos.tasks.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())
            <div class="card-foot">{{ $tasks->links() }}</div>
        @endif
    </div>
</main>
@endsection
