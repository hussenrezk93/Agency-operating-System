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
                <article class="card icon-tile tile-yellow">
                    <div class="tile-head">
                        <span class="icon-badge badge-warning"><x-icon name="clock"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_tl.waiting_assignment') }}</p>
                    </div>
                    <p class="k-value">{{ $waitingAssignment }}</p>
                </article>
                <article class="card icon-tile tile-blue">
                    <div class="tile-head">
                        <span class="icon-badge badge-info"><x-icon name="shuffle"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_tl.in_progress') }}</p>
                    </div>
                    <p class="k-value">{{ $inProgress }}</p>
                </article>
                <article class="card icon-tile tile-purple">
                    <div class="tile-head">
                        <span class="icon-badge badge-purple"><x-icon name="search"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_tl.awaiting_review') }}</p>
                    </div>
                    <p class="k-value">{{ $awaitingReview }}</p>
                </article>
                <article class="card icon-tile tile-red">
                    <div class="tile-head">
                        <span class="icon-badge badge-danger"><x-icon name="alert-triangle"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_tl.overdue') }}</p>
                    </div>
                    <p class="k-value">{{ $overdue }}</p>
                </article>
                <article class="card icon-tile tile-orange">
                    <div class="tile-head">
                        <span class="icon-badge badge-accent"><x-icon name="list-checks"/></span>
                        <p class="k-label">{{ __('agencyos.dashboard_tl.new_assignments_this_week') }}</p>
                    </div>
                    <p class="k-value">{{ $newAssignmentsTrend['current'] }}</p>
                    @if($newAssignmentsTrend['percent'] !== null)
                        <p class="k-trend-row" title="{{ __('agencyos.dashboard_manager.new_tasks_trend_hint') }}">
                            <span class="k-trend-delta k-neutral">
                                <x-icon :name="$newAssignmentsTrend['direction'] === 'down' ? 'chevron-down' : 'chevron-up'"/>
                                {{ abs($newAssignmentsTrend['percent']) }}%
                            </span>
                            {{ __('agencyos.dashboard_admin.vs_last_week') }}
                        </p>
                    @endif
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
                <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.waiting_assignment_list') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        @forelse($waitingAssignmentSteps as $step)
                            <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                <td style="text-align:end"><a class="btn btn-sm btn-outline" href="{{ route('tasks.show', $step->task_id) }}">{{ __('agencyos.dashboard_tl.assign') }}</a></td></tr>
                        @empty
                            <tr><td class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_tl.empty') }}</td></tr>
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
                    @forelse($nextDeadlines as $step)
                        <div class="list-item">
                            <div class="li-main">
                                <a class="li-title" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a>
                            </div>
                            <div class="li-end mono small">{{ $step->current_due_at?->format('M d') }}</div>
                        </div>
                    @empty
                        <div class="list-item"><span class="muted small">{{ __('agencyos.dashboard_tl.empty') }}</span></div>
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

    <div class="grid grid-2" style="align-items:start">
        <div class="card glass-dark">
            <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.my_tasks') }}</h2></div>
            <div class="table-wrap">
                <table>
                    <tbody>
                    @forelse($myTasks as $assignment)
                        <tr><td><a href="{{ route('tasks.show', $assignment->step->task_id) }}">{{ $assignment->step->task->title ?? '—' }}</a></td></tr>
                    @empty
                        <tr><td class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_tl.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card glass-dark">
            <div class="card-head"><h2>{{ __('agencyos.performance.title') }}</h2></div>
            <div class="card-body">
                <div class="kv-row"><span>{{ __('agencyos.performance.personal_score') }}</span><b>{{ $personalScore?->displayScore() ?? 'N/A' }}</b></div>
                <div class="kv-row"><span>{{ __('agencyos.performance.team_score') }}</span><b>{{ $teamScore?->displayScore() ?? 'N/A' }}</b></div>
                <a class="small" href="{{ route('reports.index') }}">{{ __('agencyos.performance.view_full') }} →</a>
            </div>
        </div>
    </div>
</main>
@endsection
