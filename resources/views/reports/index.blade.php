@extends('layouts.app')
@section('title', __('agencyos.reports.title'))
@section('page', 'reports')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.reports.title') }}</h1>
            <div class="page-sub">{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.index') }}" class="card" style="padding:12px 14px;display:flex;gap:10px;align-items:center;margin-bottom:14px">
        <select name="month" class="select" onchange="this.form.submit()">
            @foreach($availableMonths as $month)
                @php($m = \Illuminate\Support\Carbon::parse($month))
                <option value="{{ $m->format('Y-m') }}" @selected($m->format('Y-m') === \Illuminate\Support\Carbon::parse($monthStart)->format('Y-m'))>{{ $m->translatedFormat('F Y') }}</option>
            @endforeach
            @if($availableMonths->isEmpty())
                <option value="{{ \Illuminate\Support\Carbon::parse($monthStart)->format('Y-m') }}" selected>{{ \Illuminate\Support\Carbon::parse($monthStart)->translatedFormat('F Y') }}</option>
            @endif
        </select>
    </form>

    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>{{ __('agencyos.reports.department_report') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('agencyos.reports.department') }}</th><th>{{ __('agencyos.reports.due_steps') }}</th><th>{{ __('agencyos.reports.on_time') }}</th><th>{{ __('agencyos.reports.late') }}</th><th>{{ __('agencyos.reports.compliance') }}</th></tr></thead>
                <tbody>
                @forelse($departments as $snapshot)
                    <tr>
                        <td>{{ $snapshot->department->name ?? '—' }}</td>
                        <td>{{ $snapshot->due_steps }}</td>
                        <td>{{ $snapshot->on_time_steps }}</td>
                        <td>{{ $snapshot->overdue_steps }}</td>
                        <td>{{ $snapshot->displayScore() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.reports.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>{{ __('agencyos.reports.tl_report') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('agencyos.reports.team_leader') }}</th><th>{{ __('agencyos.reports.department') }}</th><th>{{ __('agencyos.reports.personal_score') }}</th><th>{{ __('agencyos.reports.team_rate') }}</th></tr></thead>
                <tbody>
                @forelse($teamLeaders as $row)
                    <tr>
                        <td>{{ $row['personal']->user->full_name ?? '—' }}</td>
                        <td>{{ $row['personal']->user->department->name ?? '—' }}</td>
                        <td>{{ $row['personal']->displayScore() }}</td>
                        <td>{{ $row['team']?->displayScore() ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.reports.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>{{ __('agencyos.reports.employee_report') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('agencyos.reports.employee') }}</th><th>{{ __('agencyos.reports.department') }}</th><th>{{ __('agencyos.reports.due_steps') }}</th><th>{{ __('agencyos.reports.on_time') }}</th><th>{{ __('agencyos.reports.late') }}</th><th>{{ __('agencyos.reports.score') }}</th></tr></thead>
                <tbody>
                @forelse($employees as $snapshot)
                    <tr>
                        <td><a href="{{ route('performance.show', $snapshot->user) }}">{{ $snapshot->user->full_name ?? '—' }}</a></td>
                        <td>{{ $snapshot->user->department->name ?? '—' }}</td>
                        <td>{{ $snapshot->due_steps }}</td>
                        <td>{{ $snapshot->on_time_steps }}</td>
                        <td>{{ $snapshot->overdue_steps }}</td>
                        <td>{{ $snapshot->displayScore() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.reports.no_data') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
