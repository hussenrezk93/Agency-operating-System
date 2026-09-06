@extends('layouts.app')
@section('title', __('agencyos.dashboard.page_title'))
@section('page', 'dashboard')
@section('page_header')
    @php
        $ar = app()->isLocale('ar');
        $hour = now()->hour;
        $greeting = $ar ? ($hour < 12 ? 'صباح الخير' : 'مساء الخير') : ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'));
        $firstName = trim(explode(' ', auth()->user()->full_name ?: auth()->user()->username)[0]);
    @endphp
    <div class="dx-greet">
        <h1>{{ $greeting }}, {{ $firstName }}</h1>
        <p>{{ $ar ? 'تابع مهامك، راقب التقدم، وتتبع الحالة.' : 'Stay on top of your tasks, monitor progress, and track status.' }}</p>
    </div>
    <nav class="dx-seg" aria-label="Dashboard">
        <a class="is-current" href="{{ route('dashboard') }}" aria-current="page"><span class="ic"><x-icon name="layout-grid"/></span><span>{{ $ar ? 'نظرة عامة' : 'Overview' }}</span></a>
        <a href="{{ route('tasks.index') }}"><span class="ic"><x-icon name="list-checks"/></span><span>{{ $ar ? 'مهامي' : 'My tasks' }}</span></a>
        <a href="{{ route('projects.index') }}"><span class="ic"><x-icon name="folder-kanban"/></span><span>{{ $ar ? 'المشروعات' : 'Projects' }}</span></a>
        <a href="{{ route('performance.show') }}"><span class="ic"><x-icon name="bar-chart-3"/></span><span>{{ $ar ? 'الأداء' : 'Performance' }}</span></a>
    </nav>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <div class="dashboard-modern">
    @php
        $segs = collect($statusSegments)->values();
        $openStepsTotal = $segs->sum('count');
        $pct = fn ($count) => $openStepsTotal > 0 ? round($count / $openStepsTotal * 100) : 0;
        $pillClass = ['b-waiting' => 'is-muted', 'b-progress' => 'is-info', 'b-review' => 'is-accent', 'b-changes' => 'is-warning', 'b-approved' => 'is-success', 'b-neutral' => 'is-muted', 'b-cancel' => 'is-muted'];
        $tasksSearchId = 'dxs-'.\Illuminate\Support\Str::random(8);
    @endphp

    <div class="dx-grid" style="margin-bottom:18px">
        <div class="dx-c4">
            <section class="dx-card dx-hero dx-fill">
                <div class="dx-hero-top">
                    <span class="dx-hero-label">{{ $ar ? 'أعمالي المفتوحة' : 'My open work' }}</span>
                    <span class="dx-hero-chip"><x-icon name="user"/>{{ auth()->user()->department?->name }}</span>
                </div>
                <p class="dx-hero-value">{{ $openStepsTotal }}<span class="dx-hero-unit">{{ $ar ? 'خطوة' : 'steps' }}</span></p>
                <div class="dx-hero-actions">
                    <a class="dx-btn dx-btn-dark" href="{{ route('tasks.index') }}"><x-icon name="list-checks"/>{{ $ar ? 'مهامي' : 'My tasks' }}</a>
                    <a class="dx-btn dx-btn-soft" href="{{ route('performance.show') }}"><x-icon name="bar-chart-3"/>{{ $ar ? 'أدائي' : 'My Performance' }}</a>
                </div>
                <div class="dx-subs">
                    <div class="dx-subs-head">
                        <b>{{ $ar ? 'حالة مهامي' : 'My task status' }}</b>
                        <span>{{ $ar ? 'خطوات مفتوحة' : 'Open steps' }}: {{ $openStepsTotal }}</span>
                    </div>
                    <div class="dx-subs-grid">
                        <div class="dx-sub">
                            <div class="dx-sub-top">
                                <span class="dx-sub-dot" style="background:var(--color-info)"></span>
                                <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_in_progress') }}">{{ __('agencyos.dashboard_admin.column_in_progress') }}</span>
                            </div>
                            <div class="dx-sub-value">{{ $segs[0]['count'] ?? 0 }}</div>
                            <div class="dx-sub-note">{{ $pct($segs[0]['count'] ?? 0) }}%</div>
                        </div>
                        <div class="dx-sub">
                            <div class="dx-sub-top">
                                <span class="dx-sub-dot" style="background:var(--color-primary)"></span>
                                <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_under_review') }}">{{ __('agencyos.dashboard_admin.column_under_review') }}</span>
                            </div>
                            <div class="dx-sub-value">{{ $segs[1]['count'] ?? 0 }}</div>
                            <div class="dx-sub-note">{{ $pct($segs[1]['count'] ?? 0) }}%</div>
                        </div>
                        <div class="dx-sub">
                            <div class="dx-sub-top">
                                <span class="dx-sub-dot" style="background:var(--color-warning)"></span>
                                <span class="dx-sub-name" title="{{ __('agencyos.tasks.actions.request_changes') }}">{{ __('agencyos.tasks.actions.request_changes') }}</span>
                            </div>
                            <div class="dx-sub-value">{{ $segs[2]['count'] ?? 0 }}</div>
                            <div class="dx-sub-note">{{ $pct($segs[2]['count'] ?? 0) }}%</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="dx-c4">
            <div class="dx-stats">
                <article class="dx-stat is-accent">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_employee.current') }}</span>
                        <span class="dx-stat-ic"><x-icon name="shuffle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $current }}</p>
                    <p class="dx-stat-foot">{{ $ar ? 'مُسندة إليك الآن' : 'Assigned to you right now' }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_employee.overdue') }}</span>
                        <span class="dx-stat-ic"><x-icon name="alert-triangle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $overdue }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_employee.changes_requested') }}</span>
                        <span class="dx-stat-ic"><x-icon name="edit"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $changesRequested }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_employee.completed') }}</span>
                        <span class="dx-stat-ic"><x-icon name="check-circle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $completed }}</p>
                </article>
            </div>
        </div>

        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-head">
                    <div>
                        <h2>{{ $ar ? 'نشاطي — آخر 14 يومًا' : 'My activity — last 14 days' }}</h2>
                        <p>{{ $ar ? 'التغيرات في الحالة، آخر 14 يومًا' : 'Status changes, last 14 days' }}</p>
                    </div>
                </div>
                <x-dx-chart :series="$activitySeries" :unit="$ar ? 'حدث' : 'events'" :title="$ar ? 'نشاط المهام' : 'Task activity'"/>
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
        <div class="dx-c8">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_employee.my_tasks') }}</h2></div>
                    <div class="dx-toolbar">
                        <label class="dx-search" for="{{ $tasksSearchId }}">
                            <x-icon name="search"/>
                            <input type="search" id="{{ $tasksSearchId }}" data-dx-target="dx-emp-tasks" placeholder="{{ $ar ? 'البحث عن مهمة...' : 'Search tasks...' }}" aria-label="{{ $ar ? 'البحث عن مهمة...' : 'Search tasks...' }}" autocomplete="off">
                        </label>
                        <a class="dx-icon-btn" href="{{ route('tasks.index') }}" aria-label="{{ $ar ? 'عرض الكل' : 'View all' }}"><x-icon name="arrow-right"/></a>
                    </div>
                </div>
                <div class="dx-table-wrap">
                    <table class="dx-table" id="dx-emp-tasks">
                        <thead>
                            <tr>
                                <th>{{ $ar ? 'المهمة' : 'Task' }}</th>
                                <th>{{ $ar ? 'الحالة' : 'Status' }}</th>
                                <th>{{ $ar ? 'الاستحقاق' : 'Due' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($myTasks as $assignment)
                                @php($badge = \App\Support\TaskPresenter::workflowBadge($assignment->step->workflow_status, $assignment->is_self_assigned))
                                <tr>
                                    <td>
                                        <div class="dx-cell">
                                            <span class="dx-row-ic"><x-icon name="list-checks"/></span>
                                            <div>
                                                <a class="dx-td-main" href="{{ route('tasks.show', $assignment->step->task_id) }}">{{ $assignment->step->task->title ?? '—' }}</a>
                                                <span class="dx-td-sub">{{ $assignment->step->task->task_code ?? '—' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="dx-pill {{ $pillClass[$badge['class']] ?? 'is-muted' }}"><i></i>{{ $badge['label'] }}</span></td>
                                    <td class="dx-td-num">{{ $assignment->step->current_due_at?->translatedFormat('M d') ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="dx-empty-cell">{{ __('agencyos.dashboard_employee.no_tasks') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <script>
        (function () {
            var input = document.getElementById({{ \Illuminate\Support\Js::from($tasksSearchId) }});
            if (!input) return;
            var table = document.getElementById(input.dataset.dxTarget);
            if (!table) return;
            var body = table.tBodies[0];
            if (!body) return;
            var rows = Array.prototype.filter.call(body.rows, function (row) { return !row.querySelector('.dx-empty-cell'); });
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
            input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { input.value = ''; apply(); } });
        })();
        </script>

        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div>
                        <h2>{{ __('agencyos.performance.my_score') }}</h2>
                        <p>{{ now()->translatedFormat('F Y') }}</p>
                    </div>
                </div>
                <div class="dx-card-body">
                    <div class="dx-score">
                        <span class="dx-score-value">{{ $score?->displayScore() ?? 'N/A' }}</span>
                    </div>
                    <p class="dx-stat-foot" style="margin-top:10px">
                        {{ $score && ! $score->isNotApplicable() ? $score->on_time_steps.' '.($ar ? 'خطوة في الموعد' : 'on-time steps') : ($ar ? 'لا توجد بيانات بعد' : 'No data yet') }}
                    </p>
                </div>
                <a class="dx-more" href="{{ route('performance.show') }}">{{ __('agencyos.performance.view_full') }}<x-icon name="arrow-right"/></a>
            </section>
        </div>
    </div>

    <div class="dx-grid">
        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_tl.next_deadlines') }}</h2></div>
                </div>
                <div class="dx-list">
                    @forelse($upcomingDeadlines as $assignment)
                        <div class="dx-list-item">
                            <span class="dx-row-ic"><x-icon name="calendar"/></span>
                            <div class="dx-list-main">
                                <a class="dx-list-title" href="{{ route('tasks.show', $assignment->step->task_id) }}">{{ $assignment->step->task->title ?? '—' }}</a>
                            </div>
                            <span class="dx-list-end">{{ $assignment->step->current_due_at->translatedFormat('M d') }}</span>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_employee.no_tasks') }}</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.notifications.index.title') }}</h2></div>
                    <div class="dx-tools">
                        <a class="dx-icon-btn" href="{{ route('notifications.index') }}" aria-label="{{ $ar ? 'عرض الكل' : 'View all' }}"><x-icon name="arrow-right"/></a>
                    </div>
                </div>
                <div class="dx-list">
                    @forelse($recentNotifications as $notification)
                        <div class="dx-list-item">
                            <span class="dx-row-ic"><x-icon name="bell"/></span>
                            <div class="dx-list-main">
                                <span class="dx-list-title">{{ $notification->title }}</span>
                                <span class="dx-list-sub">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.notifications.index.empty') }}</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-body">
                    <x-mini-calendar heading="{{ $ar ? 'التقويم' : 'Calendar' }}"/>
                </div>
            </section>
        </div>
    </div>
    </div>

    {{-- Pre-redesign dashboard body, kept ONLY for mobile (see .dashboard-legacy in
         agencyos.css) — the shared controller already returns every variable this
         needs, since the new template above reuses the same names. --}}
    <div class="dashboard-legacy">
        <div class="grid-2" style="align-items:start">
            <div class="card">
                <div class="card-head"><h2>{{ __('agencyos.dashboard_employee.my_tasks') }}</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>{{ __('agencyos.dashboard_employee.task') }}</th><th>{{ __('agencyos.dashboard_employee.status') }}</th><th>{{ __('agencyos.dashboard_employee.due') }}</th></tr></thead>
                        <tbody>
                        @forelse($myTasks as $assignment)
                            @php($legacyBadge = \App\Support\TaskPresenter::workflowBadge($assignment->step->workflow_status, $assignment->is_self_assigned))
                            <tr>
                                <td><a href="{{ route('tasks.show', $assignment->step->task_id) }}">{{ $assignment->step->task->title ?? '—' }}</a></td>
                                <td><span class="badge {{ $legacyBadge['class'] }}"><span class="bdot"></span>{{ $legacyBadge['label'] }}</span></td>
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

        <div class="card" style="margin-top:18px">
            <div class="card-head"><h2>{{ __('agencyos.dashboard_tl.next_deadlines') }}</h2></div>
            <div class="card-body" style="padding:8px 16px">
                @forelse($upcomingDeadlines->take(3) as $assignment)
                    <div class="kv-row"><span>{{ $assignment->step->task->title ?? '—' }}</span><b class="mono small">{{ $assignment->step->current_due_at?->translatedFormat('M d') }}</b></div>
                @empty
                    <span class="muted small">{{ __('agencyos.dashboard_employee.no_tasks') }}</span>
                @endforelse
            </div>
        </div>

        <div class="kpi-grid" style="margin-top:18px">
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.current') }}</div><div class="k-value">{{ $current }}</div></article>
            <article class="kpi k-accent"><div class="k-label">{{ __('agencyos.dashboard_employee.overdue') }}</div><div class="k-value">{{ $overdue }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.changes_requested') }}</div><div class="k-value">{{ $changesRequested }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_employee.completed') }}</div><div class="k-value">{{ $completed }}</div></article>
        </div>
    </div>
</main>
@endsection
