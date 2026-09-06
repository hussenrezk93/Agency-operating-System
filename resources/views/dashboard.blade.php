@extends('layouts.app')
@section('title', __('agencyos.dashboard.page_title'))
@section('page', 'dashboard')
@section('page_header')
    @php
        $ar = app()->isLocale('ar');
        $hour = now()->hour;
        $greeting = $ar ? ($hour < 12 ? 'صباح الخير' : 'مساء الخير') : ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'));
        $firstName = trim(explode(' ', $user->full_name ?: $user->username)[0]);
    @endphp
    <div class="dx-greet">
        <h1>{{ $greeting }}, {{ $firstName }}</h1>
        <p>{{ $ar ? 'نظرة عامة على الهيكل التنظيمي والموظفين وصحة سير العمل.' : 'Organisation structure, people, and workflow health at a glance.' }}</p>
    </div>
    <nav class="dx-seg" aria-label="Dashboard">
        <a class="is-current" href="{{ route('dashboard') }}" aria-current="page"><span class="ic"><x-icon name="layout-grid"/></span><span>{{ $ar ? 'نظرة عامة' : 'Overview' }}</span></a>
        <a href="{{ route('users.index') }}"><span class="ic"><x-icon name="users"/></span><span>{{ $ar ? 'المستخدمون' : 'Users' }}</span></a>
        <a href="{{ route('departments.index') }}"><span class="ic"><x-icon name="building-2"/></span><span>{{ $ar ? 'الأقسام' : 'Departments' }}</span></a>
        <a href="{{ route('audit-log.index') }}"><span class="ic"><x-icon name="shield-check"/></span><span>{{ $ar ? 'سجل التدقيق' : 'Audit log' }}</span></a>
    </nav>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))<div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>@endif

    @isset($departmentSummaries)
        @php
            // Not returned by the controller as its own total (only per-department, to
            // avoid double-counting overdue on top of it) — summed here from the same
            // $departmentSummaries rows the by-department table already renders.
            $openSteps = $totals['waitingAssignment'] + $totals['inProgress'] + $totals['underReview']
                + $departmentSummaries->sum('changesRequested');
        @endphp

        <div class="dashboard-modern">
        <div class="dx-grid" style="margin-bottom:18px">
            <div class="dx-c4">
                <section class="dx-card dx-hero dx-fill">
                    <div class="dx-hero-top">
                        <span class="dx-hero-label">{{ $ar ? 'خطوات مفتوحة' : 'Open steps' }}</span>
                        <span class="dx-hero-chip"><x-icon name="building-2"/>{{ $ar ? 'المؤسسة' : 'Organisation' }}</span>
                    </div>
                    <p class="dx-hero-value">{{ $openSteps }}<span class="dx-hero-unit">{{ $ar ? 'خطوة' : 'steps' }}</span></p>
                    <div class="dx-hero-actions">
                        <a class="dx-btn dx-btn-dark" href="{{ route('users.index') }}"><x-icon name="users"/>{{ $ar ? 'المستخدمون' : 'Users' }}</a>
                        <a class="dx-btn dx-btn-soft" href="{{ route('departments.index') }}"><x-icon name="building-2"/>{{ $ar ? 'الأقسام' : 'Department' }}</a>
                    </div>
                    <div class="dx-subs">
                        <div class="dx-subs-head">
                            <b>{{ $ar ? 'التوزيع' : 'Breakdown' }}</b>
                            <span>{{ $departmentSummaries->count() }} {{ $ar ? 'أقسام' : 'departments' }}</span>
                        </div>
                        <div class="dx-subs-grid">
                            <div class="dx-sub">
                                <div class="dx-sub-top">
                                    <span class="dx-sub-dot" style="background:#94A3B8"></span>
                                    <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}">{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</span>
                                </div>
                                <div class="dx-sub-value">{{ $totals['waitingAssignment'] }}</div>
                            </div>
                            <div class="dx-sub">
                                <div class="dx-sub-top">
                                    <span class="dx-sub-dot" style="background:var(--color-info)"></span>
                                    <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_in_progress') }}">{{ __('agencyos.dashboard_admin.column_in_progress') }}</span>
                                </div>
                                <div class="dx-sub-value">{{ $totals['inProgress'] }}</div>
                            </div>
                            <div class="dx-sub">
                                <div class="dx-sub-top">
                                    <span class="dx-sub-dot" style="background:var(--color-primary)"></span>
                                    <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_under_review') }}">{{ __('agencyos.dashboard_admin.column_under_review') }}</span>
                                </div>
                                <div class="dx-sub-value">{{ $totals['underReview'] }}</div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="dx-c4">
                <div class="dx-stats">
                    <article class="dx-stat is-accent">
                        <div class="dx-stat-top">
                            <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</span>
                            <span class="dx-stat-ic"><x-icon name="clock"/></span>
                        </div>
                        <p class="dx-stat-value">{{ $totals['waitingAssignment'] }}</p>
                        <p class="dx-stat-foot">{{ $ar ? 'تحتاج إلى قائد فريق لتعيينها' : 'Needs a team leader to assign it' }}</p>
                    </article>
                    <article class="dx-stat">
                        <div class="dx-stat-top">
                            <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_in_progress') }}</span>
                            <span class="dx-stat-ic"><x-icon name="shuffle"/></span>
                        </div>
                        <p class="dx-stat-value">{{ $totals['inProgress'] }}</p>
                    </article>
                    <article class="dx-stat">
                        <div class="dx-stat-top">
                            <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_overdue') }}</span>
                            <span class="dx-stat-ic"><x-icon name="alert-triangle"/></span>
                        </div>
                        <p class="dx-stat-value">{{ $totals['overdue'] }}</p>
                    </article>
                    <article class="dx-stat">
                        <div class="dx-stat-top">
                            <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_active_projects') }}</span>
                            <span class="dx-stat-ic"><x-icon name="folder-kanban"/></span>
                        </div>
                        <p class="dx-stat-value">{{ $totals['activeProjects'] }}</p>
                    </article>
                </div>
            </div>

            <div class="dx-c4">
                <section class="dx-card dx-fill">
                    <div class="dx-card-head">
                        <div>
                            <h2>{{ $ar ? 'نشاط المهام' : 'Task activity' }}</h2>
                            <p>{{ $ar ? "المهام المنشأة، آخر {$chartRangeDays} يومًا" : "Tasks created, last {$chartRangeDays} days" }}</p>
                        </div>
                        <div class="dx-tools"><x-range-menu :days="$chartRangeDays"/></div>
                    </div>
                    <x-dx-chart :series="$activitySeries" :unit="$ar ? 'مهمة' : 'Tasks'" :title="$ar ? 'نشاط المهام' : 'Task activity'"/>
                    <div class="dx-card-body" style="padding-top:0">
                        <div class="dx-legend">
                            <span><i style="background:var(--dx-accent)"></i>{{ $ar ? 'النشاط' : 'Activity' }}</span>
                            <span><i style="background:var(--dx-ink)"></i>{{ $ar ? 'يوم الذروة' : 'Peak day' }}</span>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="dx-grid" style="margin-bottom:18px">
            <div class="dx-c4">
                <section class="dx-card dx-fill">
                    <div class="dx-card-head">
                        <div>
                            <h2>{{ __('agencyos.dashboard_admin.health_score') }}</h2>
                            <p>{{ $hasHealthData ? __('agencyos.dashboard_admin.health_band_'.$healthBand) : __('agencyos.dashboard_admin.health_empty') }}</p>
                        </div>
                    </div>
                    <div class="dx-card-body">
                        <div class="dx-score">
                            <span class="dx-score-value">{{ $hasHealthData ? $healthScore : '—' }}</span>
                            <span class="dx-score-max">/ 100</span>
                        </div>
                        <div class="dx-meter-track" style="margin-top:14px" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $hasHealthData ? $onTimeRate : 0 }}">
                            <div class="dx-meter-fill is-{{ $healthBand }}" style="width:{{ $hasHealthData ? $onTimeRate : 0 }}%"></div>
                        </div>
                        <div class="dx-meter-ends">
                            <span>{{ __('agencyos.dashboard_admin.legend_on_time') }} <b>{{ $hasHealthData ? $onTimeRate.'%' : '—' }}</b></span>
                            <span>{{ __('agencyos.dashboard_admin.legend_overdue') }} <b>{{ $hasHealthData ? $overdueRate.'%' : '—' }}</b></span>
                        </div>

                        <div class="dx-legend-rows">
                            <div class="dx-legend-row">
                                <i style="background:#16A34A"></i>
                                <span>{{ __('agencyos.dashboard_admin.legend_on_time') }}</span>
                                <b>{{ $hasHealthData ? $onTimeRate.'%' : '—' }}</b>
                            </div>
                            <div class="dx-legend-row">
                                <i style="background:#F59E0B"></i>
                                <span>{{ __('agencyos.dashboard_admin.legend_due_soon') }}</span>
                                <b>{{ $hasHealthData ? $dueSoonRate.'%' : '—' }}</b>
                            </div>
                            <div class="dx-legend-row">
                                <i style="background:#E11D48"></i>
                                <span>{{ __('agencyos.dashboard_admin.legend_overdue') }}</span>
                                <b>{{ $hasHealthData ? $overdueRate.'%' : '—' }}</b>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="dx-c8">
                <section class="dx-card dx-fill">
                    <div class="dx-card-head has-line">
                        <div>
                            <h2>{{ __('agencyos.dashboard_admin.by_department') }}</h2>
                            <p>{{ $ar ? 'إجماليات فقط — لا محتوى مهام' : 'Aggregate counts only — no task content' }}</p>
                        </div>
                        <div class="dx-toolbar">
                            @php($searchId = 'dxs-'.\Illuminate\Support\Str::random(8))
                            <label class="dx-search" for="{{ $searchId }}">
                                <x-icon name="search"/>
                                <input type="search" id="{{ $searchId }}" data-dx-target="dx-admin-depts" placeholder="{{ $ar ? 'البحث عن قسم...' : 'Search departments...' }}" aria-label="{{ $ar ? 'البحث عن قسم...' : 'Search departments...' }}" autocomplete="off">
                            </label>
                        </div>
                    </div>

                    <div class="dx-table-wrap">
                        <table class="dx-table" id="dx-admin-depts">
                            <thead>
                                <tr>
                                    <th>{{ __('agencyos.dashboard_admin.column_department') }}</th>
                                    <th>{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</th>
                                    <th>{{ __('agencyos.dashboard_admin.column_in_progress') }}</th>
                                    <th>{{ __('agencyos.dashboard_admin.column_overdue') }}</th>
                                    <th>{{ __('agencyos.dashboard_admin.column_active_projects') }}</th>
                                    <th>{{ __('agencyos.dashboard_admin.column_headcount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($departmentSummaries as $summary)
                                    <tr>
                                        <td>
                                            <div class="dx-cell">
                                                <span class="dx-row-ic"><x-icon name="building-2"/></span>
                                                <div><span class="dx-td-main">{{ $summary['name'] }}</span></div>
                                            </div>
                                        </td>
                                        <td class="dx-td-num">{{ $summary['waitingAssignment'] }}</td>
                                        <td class="dx-td-num">{{ $summary['inProgress'] }}</td>
                                        <td><span class="dx-pill is-{{ $summary['overdue'] > 0 ? 'danger' : 'success' }}"><i></i>{{ $summary['overdue'] }}</span></td>
                                        <td class="dx-td-num">{{ $summary['activeProjects'] }}</td>
                                        <td class="dx-td-num">{{ $summary['headcount'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="dx-empty-cell">{{ __('agencyos.dashboard_admin.empty') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
        <script>
        (function () {
            var input = document.getElementById({{ \Illuminate\Support\Js::from($searchId) }});
            if (!input) return;

            var table = document.getElementById(input.dataset.dxTarget);
            if (!table) return;

            var body = table.tBodies[0];
            if (!body) return;

            var rows = Array.prototype.filter.call(body.rows, function (row) {
                return !row.querySelector('.dx-empty-cell');
            });
            var emptyRow = body.querySelector('.dx-empty-cell');
            var noMatch = null;

            function apply() {
                var q = input.value.trim().toLowerCase();
                var shown = 0;

                rows.forEach(function (row) {
                    var hit = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1;
                    row.hidden = !hit;
                    if (hit) shown++;
                });

                if (emptyRow) return;

                if (shown === 0 && q !== '') {
                    if (!noMatch) {
                        noMatch = body.insertRow();
                        var cell = noMatch.insertCell();
                        cell.colSpan = table.rows[0] ? table.rows[0].cells.length : 1;
                        cell.className = 'dx-empty-cell';
                        cell.textContent = {{ \Illuminate\Support\Js::from($ar ? 'لا توجد صفوف مطابقة لبحثك.' : 'No rows match your search.') }};
                    }
                    noMatch.hidden = false;
                } else if (noMatch) {
                    noMatch.hidden = true;
                }
            }

            input.addEventListener('input', apply);
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { input.value = ''; apply(); }
            });
        })();
        </script>

        <div class="dx-grid">
            <div class="dx-c5">
                <section class="dx-card dx-fill">
                    <div class="dx-card-head has-line">
                        <div><h2>{{ __('agencyos.dashboard_admin.recent_activity') }}</h2></div>
                        <div class="dx-tools">
                            <a class="dx-icon-btn" href="{{ route('audit-log.index') }}" aria-label="{{ __('agencyos.dashboard_admin.recent_activity') }}"><x-icon name="arrow-right"/></a>
                        </div>
                    </div>
                    <div class="dx-list">
                        @forelse($recentActivity as $log)
                            <div class="dx-list-item">
                                <span class="dx-row-ic"><x-icon name="shield-check"/></span>
                                <div class="dx-list-main">
                                    <span class="dx-list-title">{{ \App\Support\AuditLogPresenter::describe($log) }}</span>
                                    <span class="dx-list-sub">{{ $log->actor?->full_name ?? __('agencyos.audit_log.system_actor') }}</span>
                                </div>
                                <span class="dx-list-end">{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                        @empty
                            <div class="dx-list-item"><span class="dx-list-sub">{{ __('agencyos.audit_log.empty') }}</span></div>
                        @endforelse
                    </div>
                </section>
            </div>

            <div class="dx-c4">
                <section class="dx-card dx-fill">
                    <div class="dx-card-head has-line">
                        <div><h2>{{ __('agencyos.dashboard_admin.newest_team_members') }}</h2></div>
                    </div>
                    <div class="dx-list">
                        @forelse($newestTeamMembers as $member)
                            <div class="dx-list-item">
                                <span class="dx-ava">{{ collect(preg_split('/\s+/u', trim($member->full_name)))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                                <div class="dx-list-main">
                                    <span class="dx-list-title">{{ $member->full_name }}</span>
                                    <span class="dx-list-sub">{{ $member->department?->name ?? __('agencyos.roles.'.$member->roleCode()->value) }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="dx-list-item"><span class="dx-list-sub">{{ __('agencyos.dashboard_admin.empty') }}</span></div>
                        @endforelse
                    </div>
                    <a class="dx-more" href="{{ route('users.index') }}">{{ $ar ? 'عرض الكل' : 'View all' }}<x-icon name="arrow-right"/></a>
                </section>
            </div>

            <div class="dx-c3">
                <section class="dx-card dx-fill">
                    <div class="dx-card-body">
                        <x-mini-calendar heading="{{ __('agencyos.dashboard_admin.calendar') }}"/>
                    </div>
                </section>
            </div>
        </div>
        </div>

        {{-- Mobile-only condensed view — the full dx- layout above is 8 stacked
             full-width sections once every .dx-c* column collapses to one column,
             which reads as excessively long on a phone. No historical "old admin
             dashboard" exists to restore (its pre-redesign version only ever showed
             fabricated demo data) — this is a fresh, deliberately short summary
             instead, built from the same real $departmentSummaries/$totals. --}}
        <div class="dashboard-legacy">
            <div class="dx-stats">
                <article class="dx-stat is-accent">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</span>
                        <span class="dx-stat-ic"><x-icon name="clock"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $totals['waitingAssignment'] }}</p>
                    <p class="dx-stat-foot">{{ $ar ? 'تحتاج إلى قائد فريق لتعيينها' : 'Needs a team leader to assign it' }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_in_progress') }}</span>
                        <span class="dx-stat-ic"><x-icon name="shuffle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $totals['inProgress'] }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_overdue') }}</span>
                        <span class="dx-stat-ic"><x-icon name="alert-triangle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $totals['overdue'] }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_admin.column_active_projects') }}</span>
                        <span class="dx-stat-ic"><x-icon name="folder-kanban"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $totals['activeProjects'] }}</p>
                </article>
            </div>

            <div class="card dept-table-card" style="margin-top:18px">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_admin.by_department') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('agencyos.dashboard_admin.column_department') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_in_progress') }}</th>
                                <th>{{ __('agencyos.dashboard_admin.column_overdue') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($departmentSummaries as $summary)
                                <tr>
                                    <td>{{ $summary['name'] }}</td>
                                    <td class="mono small">{{ $summary['waitingAssignment'] }}</td>
                                    <td class="mono small">{{ $summary['inProgress'] }}</td>
                                    <td><span class="badge {{ $summary['overdue'] > 0 ? 'b-overdue' : 'b-approved' }}">{{ $summary['overdue'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.dashboard_admin.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endisset
</main>
@endsection
