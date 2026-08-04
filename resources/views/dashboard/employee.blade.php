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
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.current') }}</div><div class="k-value">{{ $current }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.overdue') }}</div><div class="k-value">{{ $overdue }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.changes_requested') }}</div><div class="k-value">{{ $changesRequested }}</div></article>
        <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.completed') }}</div><div class="k-value">{{ $completed }}</div></article>
    </div>

    <div class="grid-2" style="align-items:start">
        <div class="card">
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

        <div class="card">
            <div class="card-head"><h2>{{ __('agencyos.performance.my_score') }}</h2></div>
            <div class="card-body">
                <div class="k-value" style="font-size:36px">{{ $score?->displayScore() ?? 'N/A' }}</div>
                @if($score && ! $score->isNotApplicable())
                    <div class="small muted">{{ __('agencyos.performance.due_on_time', ['on_time' => $score->on_time_steps, 'due' => $score->due_steps]) }}</div>
                @endif
                <a class="small" href="{{ route('performance.show') }}">{{ __('agencyos.performance.view_full') }} →</a>
            </div>
        </div>
    </div>
</main>
@endsection
