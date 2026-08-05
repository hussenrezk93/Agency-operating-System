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
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.waiting_assignment') }}</div><div class="k-value">{{ $waitingAssignment }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.in_progress') }}</div><div class="k-value">{{ $inProgress }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.awaiting_review') }}</div><div class="k-value">{{ $awaitingReview }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.overdue') }}</div><div class="k-value">{{ $overdue }}</div></article>
    </div>

    <div class="grid-2" style="align-items:start">
        <div>
            <div class="card" style="margin-bottom:18px">
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

            <div class="card" style="margin-bottom:18px">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.submissions_awaiting_review') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <tbody>
                        @forelse($submissionsAwaitingReview as $step)
                            <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                <td class="small muted">{{ $step->activeAssignment?->assignee?->full_name ?? '—' }}</td></tr>
                        @empty
                            <tr><td class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_tl.empty') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.tasks_by_employee') }}</h2></div>
                <div class="card-body">
                    @forelse($tasksByEmployee as $assignments)
                        <div class="kv-row" style="display:block;margin-bottom:8px">
                            <div style="font-weight:700">{{ $assignments->first()->assignee->full_name ?? '—' }}</div>
                            @foreach($assignments as $a)
                                <div class="small muted" style="display:flex;align-items:center;gap:8px;justify-content:space-between">
                                    <span>{{ $a->step->task->title ?? '—' }}</span>
                                    @if($a->first_seen_at)
                                        <span class="seen-badge seen-yes">{{ __('agencyos.dashboard_tl.opened_at', ['time' => $a->first_seen_at->format('M d, H:i')]) }}</span>
                                    @else
                                        <span class="seen-badge seen-no">{{ __('agencyos.dashboard_tl.not_opened') }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <span class="muted small">{{ __('agencyos.dashboard_tl.empty') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div>
            <div class="card" style="margin-bottom:18px">
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

            <div class="card" style="margin-bottom:18px">
                <div class="card-head"><h2>{{ __('agencyos.performance.title') }}</h2></div>
                <div class="card-body">
                    <div class="kv-row"><span>{{ __('agencyos.performance.personal_score') }}</span><b>{{ $personalScore?->displayScore() ?? 'N/A' }}</b></div>
                    <div class="kv-row"><span>{{ __('agencyos.performance.team_score') }}</span><b>{{ $teamScore?->displayScore() ?? 'N/A' }}</b></div>
                    <a class="small" href="{{ route('reports.index') }}">{{ __('agencyos.performance.view_full') }} →</a>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.next_deadlines') }}</h2></div>
                <div class="card-body">
                    @forelse($nextDeadlines as $step)
                        <div class="kv-row"><span>{{ $step->task->title ?? '—' }}</span><b class="mono small">{{ $step->current_due_at?->format('Y-m-d') }}</b></div>
                    @empty
                        <span class="muted small">{{ __('agencyos.dashboard_tl.empty') }}</span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
