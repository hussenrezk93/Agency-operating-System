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
                <article class="card icon-tile tile-blue">
                    <div class="tile-head">
                        <span class="icon-badge badge-info"><x-icon name="shuffle"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_employee.current') }}</p>
                    </div>
                    <p class="k-value">{{ $current }}</p>
                </article>
                <article class="card icon-tile tile-red">
                    <div class="tile-head">
                        <span class="icon-badge badge-danger"><x-icon name="alert-triangle"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_employee.overdue') }}</p>
                    </div>
                    <p class="k-value">{{ $overdue }}</p>
                </article>
                <article class="card icon-tile tile-yellow">
                    <div class="tile-head">
                        <span class="icon-badge badge-warning"><x-icon name="edit"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_employee.changes_requested') }}</p>
                    </div>
                    <p class="k-value">{{ $changesRequested }}</p>
                </article>
                <article class="card icon-tile tile-green">
                    <div class="tile-head">
                        <span class="icon-badge badge-success"><x-icon name="check-circle"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_employee.completed') }}</p>
                    </div>
                    <p class="k-value">{{ $completed }}</p>
                </article>
            </div>

            <div class="grid grid-main" style="align-items:stretch">
                <div class="card glass-dark">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_employee.my_activity') }}</h2></div>
                    <div class="card-body"><x-line-chart :series="$activitySeries"/></div>
                </div>
                <div class="card card-body">
                    <div class="k-label" style="margin-bottom:4px">{{ __('agencyos.dashboard_employee.my_status') }}</div>
                    <x-donut-chart :segments="$statusSegments" :center-value="collect($statusSegments)->sum('count')" :center-label="__('agencyos.dashboard_manager.total_open_steps')"/>
                </div>
            </div>

            <div class="card glass-dark">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_employee.my_tasks') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>{{ __('agencyos.dashboard_employee.task') }}</th><th>{{ __('agencyos.dashboard_employee.status') }}</th><th>{{ __('agencyos.dashboard_employee.due') }}</th></tr></thead>
                        <tbody>
                        @forelse($myTasks as $assignment)
                            @php($badge = \App\Support\TaskPresenter::workflowBadge($assignment->step->workflow_status))
                            <tr>
                                <td><a href="{{ route('tasks.show', $assignment->step->task_id) }}">{{ $assignment->step->task->title ?? '—' }}</a></td>
                                <td><span class="badge {{ $badge['class'] }}"><span class="bdot"></span>{{ $badge['label'] }}</span></td>
                                <td class="mono small">{{ $assignment->step->current_due_at?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.dashboard_employee.no_tasks') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card glass-orange">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.next_deadlines') }}</h2></div>
                <div class="list">
                    @forelse($upcomingDeadlines as $assignment)
                        <div class="list-item">
                            <div class="li-main">
                                <a class="li-title" href="{{ route('tasks.show', $assignment->step->task_id) }}">{{ $assignment->step->task->title ?? '—' }}</a>
                            </div>
                            <div class="li-end mono small">{{ $assignment->step->current_due_at->format('M d') }}</div>
                        </div>
                    @empty
                        <div class="list-item"><span class="muted small">{{ __('agencyos.dashboard_employee.no_tasks') }}</span></div>
                    @endforelse
                </div>
            </div>

            <div class="card glass-orange">
                <div class="card-head"><h2>{{ __('agencyos.notifications.index.title') }}</h2></div>
                <div class="list">
                    @forelse($recentNotifications as $notification)
                        <div class="list-item">
                            <div class="li-main">
                                <div class="li-title">{{ $notification->title }}</div>
                                <div class="li-sub">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="list-item"><span class="muted small">{{ __('agencyos.notifications.index.empty') }}</span></div>
                    @endforelse
                </div>
            </div>

            <div class="card card-body glass-orange">
                <x-mini-calendar/>
            </div>
        </div>
    </div>

    <div class="card glass-dark">
        <div class="card-head"><h2>{{ __('agencyos.performance.my_score') }}</h2></div>
        <div class="card-body">
            <div class="k-value" style="font-size:36px">{{ $score?->displayScore() ?? 'N/A' }}</div>
            @if($score && ! $score->isNotApplicable())
                <div class="small muted">{{ __('agencyos.performance.due_on_time', ['on_time' => $score->on_time_steps, 'due' => $score->due_steps]) }}</div>
            @endif
            <a class="small" href="{{ route('performance.show') }}">{{ __('agencyos.performance.view_full') }} →</a>
        </div>
    </div>
</main>
@endsection
