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
        <p>{{ $ar ? 'عيّن راجع وحافظ على سير عمل قسمك.' : 'Assign, review, and keep your department moving.' }}</p>
    </div>
    <nav class="dx-seg" aria-label="Dashboard">
        <a class="is-current" href="{{ route('dashboard') }}" aria-current="page"><span class="ic"><x-icon name="layout-grid"/></span><span>{{ $ar ? 'نظرة عامة' : 'Overview' }}</span></a>
        <a href="{{ route('tasks.index') }}"><span class="ic"><x-icon name="list-checks"/></span><span>{{ $ar ? 'المهام' : 'Tasks' }}</span></a>
        <a href="{{ route('tasks.index', ['status' => \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW]) }}"><span class="ic"><x-icon name="search"/></span><span>{{ $ar ? 'قائمة المراجعة' : 'Review' }}</span></a>
        <a href="{{ route('projects.index') }}"><span class="ic"><x-icon name="folder-kanban"/></span><span>{{ $ar ? 'المشروعات' : 'Projects' }}</span></a>
        <a href="{{ route('reports.index') }}"><span class="ic"><x-icon name="bar-chart-3"/></span><span>{{ $ar ? 'التقارير' : 'Reports' }}</span></a>
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
        $waitingSearchId = 'dxs-'.\Illuminate\Support\Str::random(8);
    @endphp

    <div class="dx-grid" style="margin-bottom:18px">
        <div class="dx-c4">
            <section class="dx-card dx-hero dx-fill">
                <div class="dx-hero-top">
                    <span class="dx-hero-label">{{ $ar ? 'خطوات مفتوحة' : 'Open steps' }}</span>
                    <span class="dx-hero-chip"><x-icon name="building-2"/>{{ auth()->user()->department?->name }}</span>
                </div>
                <p class="dx-hero-value">{{ $openStepsTotal }}<span class="dx-hero-unit">{{ $ar ? 'خطوة' : 'steps' }}</span></p>
                <div class="dx-hero-actions">
                    <a class="dx-btn dx-btn-dark" href="{{ route('tasks.index', ['status' => \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW]) }}"><x-icon name="search"/>{{ $ar ? 'بانتظار مراجعتي' : 'Awaiting my review' }}</a>
                    <a class="dx-btn dx-btn-soft" href="{{ route('tasks.index') }}"><x-icon name="list-checks"/>{{ $ar ? 'كل المهام' : 'All tasks' }}</a>
                </div>
                <div class="dx-subs">
                    <div class="dx-subs-head">
                        <b>{{ $ar ? 'توزيع حالة المهام' : 'Task status distribution' }}</b>
                        <span>{{ $ar ? 'خطوات مفتوحة' : 'Open steps' }}: {{ $openStepsTotal }}</span>
                    </div>
                    <div class="dx-subs-grid">
                        <div class="dx-sub">
                            <div class="dx-sub-top">
                                <span class="dx-sub-dot" style="background:#94A3B8"></span>
                                <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}">{{ __('agencyos.dashboard_admin.column_waiting_assignment') }}</span>
                            </div>
                            <div class="dx-sub-value">{{ $segs[0]['count'] ?? 0 }}</div>
                            <div class="dx-sub-note">{{ $pct($segs[0]['count'] ?? 0) }}%</div>
                        </div>
                        <div class="dx-sub">
                            <div class="dx-sub-top">
                                <span class="dx-sub-dot" style="background:var(--color-info)"></span>
                                <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_in_progress') }}">{{ __('agencyos.dashboard_admin.column_in_progress') }}</span>
                            </div>
                            <div class="dx-sub-value">{{ $segs[1]['count'] ?? 0 }}</div>
                            <div class="dx-sub-note">{{ $pct($segs[1]['count'] ?? 0) }}%</div>
                        </div>
                        <div class="dx-sub">
                            <div class="dx-sub-top">
                                <span class="dx-sub-dot" style="background:var(--color-primary)"></span>
                                <span class="dx-sub-name" title="{{ __('agencyos.dashboard_admin.column_under_review') }}">{{ __('agencyos.dashboard_admin.column_under_review') }}</span>
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
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_tl.waiting_assignment') }}</span>
                        <span class="dx-stat-ic"><x-icon name="clock"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $waitingAssignment }}</p>
                    <p class="dx-stat-foot">{{ $ar ? 'بانتظار تعيينك لها' : 'Waiting for you to assign' }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_tl.in_progress') }}</span>
                        <span class="dx-stat-ic"><x-icon name="shuffle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $inProgress }}</p>
                </article>
                <a href="{{ route('tasks.index', ['status' => \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW]) }}" class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_tl.awaiting_review') }}</span>
                        <span class="dx-stat-ic"><x-icon name="search"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $awaitingReview }}</p>
                </a>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_tl.overdue') }}</span>
                        <span class="dx-stat-ic"><x-icon name="alert-triangle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $overdue }}</p>
                </article>
            </div>
        </div>

        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-head">
                    <div>
                        <h2>{{ $ar ? 'نشاط المهام' : 'Task activity' }}</h2>
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
        <div class="dx-c6">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div>
                        <h2>{{ __('agencyos.dashboard_tl.waiting_assignment_list') }}</h2>
                        <p>{{ $ar ? 'بانتظار تعيينك لها' : 'Waiting for you to assign' }}</p>
                    </div>
                    <div class="dx-toolbar">
                        <label class="dx-search" for="{{ $waitingSearchId }}">
                            <x-icon name="search"/>
                            <input type="search" id="{{ $waitingSearchId }}" data-dx-target="dx-tl-waiting" placeholder="{{ $ar ? 'البحث عن مهمة...' : 'Search tasks...' }}" aria-label="{{ $ar ? 'البحث عن مهمة...' : 'Search tasks...' }}" autocomplete="off">
                        </label>
                    </div>
                </div>
                <div class="dx-table-wrap">
                    <table class="dx-table" id="dx-tl-waiting">
                        <tbody>
                            @forelse($waitingAssignmentSteps as $step)
                                <tr>
                                    <td>
                                        <div class="dx-cell">
                                            <span class="dx-row-ic"><x-icon name="clock"/></span>
                                            <div><a class="dx-td-main" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></div>
                                        </div>
                                    </td>
                                    <td class="dx-td-end"><a class="dx-btn dx-btn-dark dx-btn-sm" href="{{ route('tasks.show', $step->task_id) }}">{{ __('agencyos.dashboard_tl.assign') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="dx-empty-cell">{{ __('agencyos.dashboard_tl.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <script>
        (function () {
            var input = document.getElementById({{ \Illuminate\Support\Js::from($waitingSearchId) }});
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

        <div class="dx-c6">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ $ar ? 'مراجعات بانتظارك' : 'Submissions awaiting review' }}</h2></div>
                    <div class="dx-toolbar">
                        <a class="dx-icon-btn" href="{{ route('tasks.index', ['status' => \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW]) }}" aria-label="{{ $ar ? 'عرض الكل' : 'View all' }}"><x-icon name="arrow-right"/></a>
                    </div>
                </div>
                <div class="dx-table-wrap">
                    <table class="dx-table" id="dx-tl-review">
                        <tbody>
                            @forelse($submissionsAwaitingReview as $step)
                                <tr>
                                    <td>
                                        <div class="dx-cell">
                                            <span class="dx-row-ic"><x-icon name="search"/></span>
                                            <div>
                                                <a class="dx-td-main" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a>
                                                <span class="dx-td-sub">{{ $step->activeAssignment?->assignee?->full_name ?? '—' }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="dx-td-end"><a class="dx-btn dx-btn-soft dx-btn-sm" href="{{ route('tasks.show', $step->task_id) }}">{{ $ar ? 'مراجعة' : 'Review' }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="dx-empty-cell">{{ __('agencyos.dashboard_tl.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="dx-grid">
        <div class="dx-c5">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div>
                        <h2>{{ $ar ? 'مهام القسم حسب الموظف' : 'Department tasks by employee' }}</h2>
                        <p>{{ $ar ? 'التكليفات المفتوحة لكل شخص' : 'Open assignments per person' }}</p>
                    </div>
                </div>
                <div class="dx-card-body">
                    @php($maxWorkload = max(1, $tasksByEmployee->map->count()->max() ?? 1))
                    @forelse($tasksByEmployee as $assignments)
                        @php($employee = $assignments->first()?->assignee)
                        @php($notOpenedCount = $assignments->whereNull('first_seen_at')->count())
                        <div class="dx-workload">
                            <div class="dx-workload-top">
                                <span class="dx-workload-name">{{ $employee?->full_name ?? '—' }}</span>
                                @if($notOpenedCount > 0)
                                    <span class="dx-pill is-warning"><i></i>{{ $ar ? 'لم تُفتح بعد' : 'Not opened yet' }} · {{ $notOpenedCount }}</span>
                                @endif
                                <b>{{ $assignments->count() }}</b>
                            </div>
                            <div class="dx-meter-track" style="height:8px">
                                <div class="dx-meter-fill" style="width:{{ round($assignments->count() / $maxWorkload * 100) }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_tl.empty') }}</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="dx-c4">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_tl.next_deadlines') }}</h2></div>
                </div>
                <div class="dx-list">
                    @forelse($nextDeadlines as $step)
                        <div class="dx-list-item">
                            <span class="dx-row-ic"><x-icon name="calendar"/></span>
                            <div class="dx-list-main">
                                <a class="dx-list-title" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a>
                                <span class="dx-list-sub">{{ $step->task->task_code ?? '—' }}</span>
                            </div>
                            <span class="dx-list-end">{{ $step->current_due_at?->translatedFormat('M d') }}</span>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_tl.empty') }}</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="dx-c3">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ $ar ? 'أدائي' : 'My Performance' }}</h2></div>
                </div>
                <div class="dx-card-body">
                    <div class="dx-score">
                        <span class="dx-score-value">{{ $personalScore?->displayScore() ?? 'N/A' }}</span>
                    </div>
                    <p class="dx-stat-foot" style="margin-top:4px">{{ __('agencyos.performance.personal_score') }}</p>

                    <div class="dx-kv" style="margin-top:12px">
                        <span>{{ __('agencyos.performance.team_score') }}</span>
                        <b>{{ $teamScore?->displayScore() ?? 'N/A' }}</b>
                    </div>
                    <div class="dx-kv">
                        <span>{{ __('agencyos.dashboard_tl.my_tasks') }}</span>
                        <b>{{ $myTasks->count() }}</b>
                    </div>
                </div>
                <a class="dx-more" href="{{ route('performance.show') }}">{{ __('agencyos.performance.view_full') }}<x-icon name="arrow-right"/></a>
            </section>
        </div>
    </div>
    </div>

    {{-- Pre-redesign dashboard body, kept ONLY for mobile (see .dashboard-legacy in
         agencyos.css) — the shared controller already returns every variable this
         needs, since the new template above reuses the same names. --}}
    <div class="dashboard-legacy">
        <div class="mini-stat-row">
            <div class="mini-stat">
                <span class="mini-stat-label">{{ __('agencyos.dashboard_tl.waiting_assignment') }}</span>
                <span class="mini-stat-value">{{ $waitingAssignment }}</span>
            </div>
            <div class="mini-stat is-accent">
                <span class="mini-stat-label">{{ __('agencyos.dashboard_tl.overdue') }}</span>
                <span class="mini-stat-value">{{ $overdue }}</span>
            </div>
            <div class="mini-stat">
                <span class="mini-stat-label">{{ __('agencyos.dashboard_tl.awaiting_review') }}</span>
                <span class="mini-stat-value">{{ $awaitingReview }}</span>
            </div>
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

        <div class="kpi-grid" style="margin-top:18px">
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.waiting_assignment') }}</div><div class="k-value">{{ $waitingAssignment }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.in_progress') }}</div><div class="k-value">{{ $inProgress }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.awaiting_review') }}</div><div class="k-value">{{ $awaitingReview }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_tl.overdue') }}</div><div class="k-value">{{ $overdue }}</div></article>
        </div>
    </div>
</main>
@endsection
