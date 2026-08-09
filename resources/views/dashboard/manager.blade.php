@extends('layouts.app')
@section('title', __('agencyos.dashboard.page_title'))
@section('page', 'dashboard')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ app()->isLocale('ar') ? 'مرحبا' : 'Hi' }} {{ auth()->user()->full_name }} 👋</h1>
            <div class="page-sub">{{ now()->translatedFormat('l d M Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <div class="grid grid-main" style="align-items:start;margin-bottom:26px">
        <div class="stack">
            <div class="icon-tile-row">
                <article class="card icon-tile tile-orange">
                    <div class="tile-head">
                        <span class="icon-badge badge-accent"><x-icon name="list-checks"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_manager.active_tasks') }}</p>
                    </div>
                    <p class="k-value">{{ $activeTasks }}</p>
                    @if($newTasksTrend['percent'] !== null)
                        {{-- Neutral tone on purpose: more/fewer new tasks isn't inherently
                             good or bad (unlike overdue), so the line reports direction
                             without a green/red value judgment. --}}
                        <p class="k-trend-row" title="{{ __('agencyos.dashboard_manager.new_tasks_trend_hint') }}">
                            <span class="k-trend-delta k-neutral">
                                <x-icon :name="$newTasksTrend['direction'] === 'down' ? 'chevron-down' : 'chevron-up'"/>
                                {{ abs($newTasksTrend['percent']) }}%
                            </span>
                            {{ __('agencyos.dashboard_admin.vs_last_week') }}
                        </p>
                    @endif
                </article>
                <article class="card icon-tile tile-red">
                    <div class="tile-head">
                        <span class="icon-badge badge-danger"><x-icon name="alert-triangle"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_manager.overdue_steps') }}</p>
                    </div>
                    <p class="k-value">{{ $overdueStepsCount }}</p>
                </article>
                <article class="card icon-tile tile-yellow">
                    <div class="tile-head">
                        <span class="icon-badge badge-warning"><x-icon name="clock"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_manager.waiting_assignment') }}</p>
                    </div>
                    <p class="k-value">{{ $waitingAssignment }}</p>
                </article>
                <article class="card icon-tile tile-blue">
                    <div class="tile-head">
                        <span class="icon-badge badge-info"><x-icon name="folder-kanban"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_manager.active_projects') }}</p>
                    </div>
                    <p class="k-value">{{ $activeProjectsCount }}</p>
                </article>
            </div>

            <div class="grid grid-main" style="align-items:stretch">
                <div class="card glass-dark">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_admin.activity_title') }}</h2></div>
                    <div class="card-body"><x-line-chart :series="$activitySeries"/></div>
                </div>
                <div class="card card-body">
                    <div class="k-label" style="margin-bottom:4px">{{ __('agencyos.dashboard_manager.status_distribution') }}</div>
                    <x-donut-chart :segments="$statusSegments" :center-value="collect($statusSegments)->sum('count')" :center-label="__('agencyos.dashboard_manager.total_open_steps')"/>
                </div>
            </div>

            <div class="card glass-dark">
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
                        @forelse($redirectedAwaitingAssignment as $step)
                            <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                <td><span class="badge b-changes">{{ __('agencyos.dashboard_manager.needs_reassignment') }}</span></td>
                                <td class="small muted">{{ $step->department->name ?? '—' }}</td></tr>
                        @empty
                        @endforelse
                        @if($overdueSteps->isEmpty() && $onHoldTasks->isEmpty() && $awaitingManagerReview->isEmpty() && $redirectedAwaitingAssignment->isEmpty())
                            <tr><td class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_manager.empty') }}</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card glass-orange">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.upcoming_deadlines') }}</h2></div>
                <div class="list">
                    @forelse($upcomingDeadlines as $step)
                        <div class="list-item">
                            <div class="li-main">
                                <a class="li-title" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a>
                                <div class="li-sub">{{ $step->department->name ?? '—' }}</div>
                            </div>
                            <div class="li-end mono small">{{ $step->current_due_at->format('M d') }}</div>
                        </div>
                    @empty
                        <div class="list-item"><span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span></div>
                    @endforelse
                </div>
            </div>

            <div class="card glass-orange">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.recent_transfers') }}</h2></div>
                <div class="list">
                    @forelse($recentTransfers as $redirect)
                        <div class="list-item">
                            <div class="li-main">
                                <div class="li-title">{{ $redirect->task->title ?? '—' }}</div>
                                <div class="li-sub">{{ $redirect->fromStep->department->name ?? '—' }} → {{ $redirect->toDepartment->name ?? '—' }}</div>
                            </div>
                            <div class="li-end small muted">{{ $redirect->created_at->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="list-item"><span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span></div>
                    @endforelse
                </div>
            </div>

            <div class="card card-body glass-orange">
                <x-mini-calendar/>
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="align-items:start">
        <div>
            <div class="card glass-dark">
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
            <div class="card glass-orange" style="margin-bottom:26px">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.active_temp_tls') }}</h2></div>
                <div class="card-body">
                    @forelse($activeTempTls as $assignment)
                        <div class="kv-row"><span>{{ $assignment->department->name ?? '—' }}</span><b>{{ $assignment->user->full_name ?? '—' }}</b></div>
                    @empty
                        <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="card glass-orange">
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
