@extends('layouts.app')
@section('title', __('agencyos.dashboard.page_title'))
@section('page', 'dashboard')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ app()->isLocale('ar') ? 'مرحبا' : 'Hi' }} {{ auth()->user()->full_name }} 👋</h1>
            <div class="page-sub">{{ now()->translatedFormat('l d M Y') }}</div>
        </div>
    </div>

    <div class="kpi-grid">
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.active_tasks') }}</div><div class="k-value">{{ $activeTasks }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.overdue_steps') }}</div><div class="k-value">{{ $overdueStepsCount }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.waiting_assignment') }}</div><div class="k-value">{{ $waitingAssignment }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.active_projects') }}</div><div class="k-value">{{ $activeProjectsCount }}</div></article>
    </div>

    <div class="grid-2" style="align-items:start">
        <div>
            <div class="card" style="margin-bottom:18px">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.needs_attention') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        @forelse($overdueSteps as $step)
                            <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                <td><span class="badge b-overdue">{{ __('agencyos.dashboard_manager.overdue') }}</span></td>
                                <td class="small muted">{{ $step->department->name ?? '—' }}</td></tr>
                        @empty
                        @endforelse
                        @forelse($onHoldTasks as $task)
                            <tr><td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                                <td><span class="badge b-hold">{{ __('agencyos.dashboard_manager.on_hold') }}</span></td>
                                <td class="small muted">{{ $task->currentStep?->department?->name ?? '—' }}</td></tr>
                        @empty
                        @endforelse
                        @forelse($awaitingManagerReview as $step)
                            <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                <td><span class="badge b-review">{{ __('agencyos.dashboard_manager.your_review') }}</span></td>
                                <td class="small muted">{{ $step->department->name ?? '—' }}</td></tr>
                        @empty
                        @endforelse
                        @if($overdueSteps->isEmpty() && $onHoldTasks->isEmpty() && $awaitingManagerReview->isEmpty())
                            <tr><td class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_manager.empty') }}</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.department_on_time_rate') }}</h2></div>
                <div class="card-body">
                    @forelse($departmentScores as $snapshot)
                        <div class="kv-row"><span>{{ $snapshot->department->name ?? '—' }}</span><b>{{ $snapshot->displayScore() }}</b></div>
                    @empty
                        <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                    @endforelse
                    <a class="small" href="{{ route('reports.index') }}">{{ __('agencyos.performance.view_full') }} →</a>
                </div>
            </div>
        </div>

        <div>
            <div class="card" style="margin-bottom:18px">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.active_temp_tls') }}</h2></div>
                <div class="card-body">
                    @forelse($activeTempTls as $assignment)
                        <div class="kv-row"><span>{{ $assignment->department->name ?? '—' }}</span><b>{{ $assignment->user->full_name ?? '—' }}</b></div>
                    @empty
                        <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.active_projects_list') }}</h2></div>
                <div class="card-body">
                    @forelse($activeProjects as $project)
                        <div class="kv-row"><span><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></span><b class="small muted">{{ $project->client->name ?? '—' }}</b></div>
                    @empty
                        <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
