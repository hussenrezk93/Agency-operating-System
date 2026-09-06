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
        <div class="dx-card">
            <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.performance.this_month') }}</h2></div></div>
            <div class="dx-card-body">
                <div class="dx-score"><span class="dx-score-value">{{ $current?->displayScore() ?? 'N/A' }}</span></div>
                @if($current)
                    <div class="dx-kv" style="margin-top:12px"><span>{{ __('agencyos.performance.due_steps') }}</span><b>{{ $current->due_steps }}</b></div>
                    <div class="dx-kv"><span>{{ __('agencyos.performance.on_time_steps') }}</span><b>{{ $current->on_time_steps }}</b></div>
                    <div class="dx-kv"><span>{{ __('agencyos.performance.late_steps') }}</span><b>{{ $current->overdue_steps }}</b></div>
                @else
                    <p class="muted small">{{ __('agencyos.performance.no_due_steps') }}</p>
                @endif
                <p class="small muted" style="margin-top:12px">{{ __('agencyos.performance.formula_note') }}</p>
            </div>
        </div>

        <div class="dx-card">
            <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.performance.history') }}</h2></div></div>
            <div class="dx-card-body">
                @forelse($history as $snapshot)
                    <div class="dx-kv"><span>{{ \Illuminate\Support\Carbon::parse($snapshot->month_start)->translatedFormat('F Y') }}</span><b>{{ $snapshot->displayScore() }}</b></div>
                @empty
                    <span class="muted small">{{ __('agencyos.performance.no_history') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="dx-card" style="margin-top:18px">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.performance.recent_steps') }}</h2></div></div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead><tr><th>{{ __('agencyos.performance.task') }}</th><th>{{ __('agencyos.performance.due') }}</th><th>{{ __('agencyos.performance.outcome') }}</th></tr></thead>
                <tbody>
                @forelse($recentSteps as $step)
                    <tr>
                        <td><a class="dx-td-main" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                        <td class="dx-td-num">{{ $step->current_due_at?->format('Y-m-d') }}</td>
                        <td>
                            @if($step->workflow_status->value === 'approved' && $step->submitted_at && $step->submitted_at->lte($step->current_due_at))
                                <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.performance.on_time')]"/>
                            @elseif(in_array($step->workflow_status->value, ['cancelled', 'redirected']))
                                <x-dx-pill :badge="['class' => 'b-neutral', 'label' => __('agencyos.performance.excluded')]"/>
                            @else
                                <x-dx-pill :badge="['class' => 'b-overdue', 'label' => __('agencyos.performance.late')]"/>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="dx-empty-cell">{{ __('agencyos.performance.no_history') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
