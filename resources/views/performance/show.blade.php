@extends('layouts.app')
@section('title', __('agencyos.performance.title'))
@section('page', 'performance')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ $isSelf ? __('agencyos.performance.my_score') : $subject->full_name }}</h1>
            <div class="page-sub">{{ now()->translatedFormat('F Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <div class="grid grid-2" style="align-items:start">
        <div class="card glass-dark">
            <div class="card-head"><h2>{{ __('agencyos.performance.this_month') }}</h2></div>
            <div class="card-body">
                <div class="k-value" style="font-size:48px">{{ $current?->displayScore() ?? 'N/A' }}</div>
                @if($current)
                    <div class="kv-row"><span>{{ __('agencyos.performance.due_steps') }}</span><b>{{ $current->due_steps }}</b></div>
                    <div class="kv-row"><span>{{ __('agencyos.performance.on_time_steps') }}</span><b>{{ $current->on_time_steps }}</b></div>
                    <div class="kv-row"><span>{{ __('agencyos.performance.late_steps') }}</span><b>{{ $current->overdue_steps }}</b></div>
                @else
                    <p class="muted small">{{ __('agencyos.performance.no_due_steps') }}</p>
                @endif
                <p class="small muted" style="margin-top:12px">{{ __('agencyos.performance.formula_note') }}</p>
            </div>
        </div>

        <div class="card glass-dark">
            <div class="card-head"><h2>{{ __('agencyos.performance.history') }}</h2></div>
            <div class="card-body">
                @forelse($history as $snapshot)
                    <div class="kv-row"><span>{{ \Illuminate\Support\Carbon::parse($snapshot->month_start)->translatedFormat('F Y') }}</span><b>{{ $snapshot->displayScore() }}</b></div>
                @empty
                    <span class="muted small">{{ __('agencyos.performance.no_history') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="card glass-dark" style="margin-top:18px">
        <div class="card-head"><h2>{{ __('agencyos.performance.recent_steps') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('agencyos.performance.task') }}</th><th>{{ __('agencyos.performance.due') }}</th><th>{{ __('agencyos.performance.outcome') }}</th></tr></thead>
                <tbody>
                @forelse($recentSteps as $step)
                    <tr>
                        <td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                        <td class="mono small">{{ $step->current_due_at?->format('Y-m-d') }}</td>
                        <td>
                            @if($step->workflow_status->value === 'approved' && $step->approved_at && $step->approved_at->lte($step->current_due_at))
                                <span class="badge b-approved">{{ __('agencyos.performance.on_time') }}</span>
                            @elseif(in_array($step->workflow_status->value, ['cancelled', 'redirected']))
                                <span class="badge b-neutral">{{ __('agencyos.performance.excluded') }}</span>
                            @else
                                <span class="badge b-overdue">{{ __('agencyos.performance.late') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.performance.no_history') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
