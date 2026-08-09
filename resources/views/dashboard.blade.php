@extends('layouts.app')
@section('title', __('agencyos.dashboard.page_title'))
@section('page', 'dashboard')
@section('page_header')
    <div class="page-greet">
        <h1>{{ app()->isLocale('ar') ? 'مرحبا' : 'Hi' }} {{ $user->full_name }} 👋</h1>
        <div class="page-sub">{{ $roleLabel }} &nbsp;•&nbsp; {{ config('app.timezone') }} &nbsp;•&nbsp; {{ now()->translatedFormat('l d M Y') }}</div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))<div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>@endif

    @isset($departmentSummaries)
        <div class="grid-12">
            {{-- Row 1: stat cards (9/12) + a real, BRD-safe widget in the "right slot"
                 (3/12) — the spec's reference calls this slot "Upcoming tasks" with real
                 task titles, but BRD §15 keeps task content out of Admin's view, so
                 Recent Activity (the real, Admin-exclusive audit log) fills this slot
                 instead. Same 9/3 proportions, real Admin-appropriate content. --}}
            <div class="col-12 col-lg-9">
                <div class="icon-tile-row">
                    <article class="card icon-tile tile-orange">
                        <div class="tile-head">
                            <span class="icon-badge badge-accent"><x-icon name="clock"/></span>
                            <p class="k-label">{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</p>
                        </div>
                        <p class="k-value">{{ $totals['waitingAssignment'] }}</p>
                    </article>
                    <article class="card icon-tile tile-blue">
                        <div class="tile-head">
                            <span class="icon-badge badge-info"><x-icon name="shuffle"/></span>
                            <p class="k-label">{{ __('agencyos.dashboard_admin.column_in_progress') }}</p>
                        </div>
                        <p class="k-value">{{ $totals['inProgress'] }}</p>
                    </article>
                    <article class="card icon-tile tile-purple">
                        <div class="tile-head">
                            <span class="icon-badge badge-purple"><x-icon name="search"/></span>
                            <p class="k-label">{{ __('agencyos.dashboard_admin.column_under_review') }}</p>
                        </div>
                        <p class="k-value">{{ $totals['underReview'] }}</p>
                    </article>
                    <article class="card icon-tile tile-red">
                        <div class="tile-head">
                            <span class="icon-badge badge-danger"><x-icon name="alert-triangle"/></span>
                            <p class="k-label">{{ __('agencyos.dashboard_admin.column_overdue') }}</p>
                        </div>
                        <p class="k-value">{{ $totals['overdue'] }}</p>
                    </article>
                    <article class="card icon-tile tile-green">
                        <div class="tile-head">
                            <span class="icon-badge badge-success"><x-icon name="folder-kanban"/></span>
                            <p class="k-label">{{ __('agencyos.dashboard_admin.column_active_projects') }}</p>
                        </div>
                        <p class="k-value">{{ $totals['activeProjects'] }}</p>
                    </article>
                    <article class="card icon-tile tile-yellow">
                        <div class="tile-head">
                            <span class="icon-badge badge-warning"><x-icon name="list-checks"/></span>
                            <p class="k-label">{{ __('agencyos.dashboard_admin.new_tasks_this_week') }}</p>
                        </div>
                        <p class="k-value">{{ $newTasksTrend['current'] }}</p>
                        @if($newTasksTrend['percent'] !== null)
                            <p class="k-trend-row" title="{{ __('agencyos.dashboard_manager.new_tasks_trend_hint') }}">
                                <span class="k-trend-delta {{ $newTasksTrend['direction'] === 'down' ? 'k-down' : 'k-up' }}">
                                    <x-icon :name="$newTasksTrend['direction'] === 'down' ? 'chevron-down' : 'chevron-up'"/>
                                    {{ abs($newTasksTrend['percent']) }}%
                                </span>
                                {{ __('agencyos.dashboard_admin.vs_last_week') }}
                            </p>
                        @endif
                    </article>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div class="card glass-orange" style="height:100%">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_admin.recent_activity') }}</h2></div>
                    <div class="list">
                        @forelse($recentActivity as $log)
                            <div class="list-item">
                                <div class="li-main">
                                    <div class="li-title">{{ \App\Support\AuditLogPresenter::describe($log) }}</div>
                                    <div class="li-sub">{{ $log->actor?->full_name ?? __('agencyos.audit_log.system_actor') }} · {{ $log->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="list-item"><span class="muted small">{{ __('agencyos.audit_log.empty') }}</span></div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Row 2: chart (5/12) + health (3/12) + calendar (4/12) --}}
            <div class="col-12 col-lg-5">
                <div class="card chart-card glass-dark" style="height:100%">
                    <div class="card-head">
                        <h2>{{ __('agencyos.dashboard_admin.activity_title') }}</h2>
                        <div class="page-actions"><x-range-menu :days="$chartRangeDays"/></div>
                    </div>
                    <div class="card-body">
                        <x-line-chart :series="$activitySeries"/>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-3">
                @php
                    // Single ring = health score, banded. Geometry only — nothing here
                    // is a business calculation. r=52/stroke-width=11 inside a 120
                    // viewBox is the exact reference proportion; a round cap at a full
                    // 100% arc overlaps its own start and reads as a bump, so it's butt
                    // capped only in that one case.
                    $ringR = 52; $ringC = 2 * M_PI * $ringR;
                    $ringDash = $hasHealthData ? $healthScore / 100 * $ringC : 0;
                    $ringBand = $hasHealthData ? $healthBand : 'danger';
                    $ringCap = $healthScore >= 100 ? 'butt' : 'round';
                @endphp
                <div class="card meter-card" style="height:100%">
                    <div class="k-label">{{ __('agencyos.dashboard_admin.health_score') }}</div>
                    <div class="ring-chart">
                        <svg viewBox="0 0 120 120">
                            <circle class="ring-track track-{{ $ringBand }}" cx="60" cy="60" r="{{ $ringR }}" stroke-width="11"/>
                            <circle class="ring-fill fill-{{ $ringBand }}" cx="60" cy="60" r="{{ $ringR }}" stroke-width="11"
                                    stroke-linecap="{{ $ringCap }}" stroke-dasharray="{{ $ringDash }} {{ $ringC }}"/>
                        </svg>
                        <div class="ring-center">
                            @if($hasHealthData)
                                <p class="ring-score">{{ $healthScore }}<span class="ring-score-sup">/100</span></p>
                                <div class="ring-caption">{{ __('agencyos.dashboard_admin.health_band_'.$healthBand) }}</div>
                            @else
                                <p class="ring-score">—</p>
                                <div class="ring-caption">{{ __('agencyos.dashboard_admin.health_empty') }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="ring-legend">
                        <div class="ring-legend-row">
                            <span class="rdot rdot-success"></span>
                            <span class="rlabel">{{ __('agencyos.dashboard_admin.legend_on_time') }}</span>
                            <span class="rvalue">{{ $hasHealthData ? $onTimeRate.'%' : '—' }}</span>
                        </div>
                        <div class="ring-legend-row">
                            <span class="rdot rdot-warning"></span>
                            <span class="rlabel">{{ __('agencyos.dashboard_admin.legend_due_soon') }}</span>
                            <span class="rvalue">{{ $hasHealthData ? $dueSoonRate.'%' : '—' }}</span>
                        </div>
                        <div class="ring-legend-row">
                            <span class="rdot rdot-danger"></span>
                            <span class="rlabel">{{ __('agencyos.dashboard_admin.legend_overdue') }}</span>
                            <span class="rvalue">{{ $hasHealthData ? $overdueRate.'%' : '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card card-body glass-orange" style="height:100%">
                    <x-mini-calendar heading="{{ __('agencyos.dashboard_admin.calendar') }}"/>
                </div>
            </div>

            {{-- Row 3: by-department table (8/12) + a second real widget (4/12) — the
                 spec's "Recent tasks" table shows real task rows with assignees, which
                 BRD §15 also forbids for Admin, so the existing department-summary
                 table (counts only) keeps its place at the same width. --}}
            <div class="col-12 col-lg-8">
                <div class="card glass-dark">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_admin.by_department') }}</h2></div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>{{ __('agencyos.dashboard_admin.column_department') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_in_progress') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_overdue') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_headcount') }}</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($departmentSummaries as $summary)
                                <tr>
                                    <td><b>{{ $summary['name'] }}</b></td>
                                    <td>{{ $summary['waitingAssignment'] }}</td>
                                    <td>{{ $summary['inProgress'] }}</td>
                                    <td>{{ $summary['overdue'] }}</td>
                                    <td>{{ $summary['headcount'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_admin.empty') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card glass-orange" style="height:100%">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_admin.newest_team_members') }}</h2></div>
                    <div class="list">
                        @forelse($newestTeamMembers as $member)
                            <div class="list-item">
                                <div class="avatar sm">{{ collect(preg_split('/\s+/u', trim($member->full_name)))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</div>
                                <div class="li-main">
                                    <div class="li-title">{{ $member->full_name }}</div>
                                    <div class="li-sub">{{ $member->department?->name ?? __('agencyos.roles.'.$member->roleCode()->value) }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="list-item"><span class="muted small">{{ __('agencyos.dashboard_admin.empty') }}</span></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    @endisset
</main>
@endsection
