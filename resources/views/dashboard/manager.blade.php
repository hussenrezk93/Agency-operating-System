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
        <p>{{ $ar ? 'تابع سير العمل في كل قسم وتدخل حيث يلزم.' : 'Track delivery across every department and step in where it matters.' }}</p>
    </div>
    <nav class="dx-seg" aria-label="Dashboard">
        <a class="is-current" href="{{ route('dashboard') }}" aria-current="page"><span class="ic"><x-icon name="layout-grid"/></span><span>{{ $ar ? 'نظرة عامة' : 'Overview' }}</span></a>
        <a href="{{ route('tasks.index') }}"><span class="ic"><x-icon name="list-checks"/></span><span>{{ $ar ? 'المهام' : 'Tasks' }}</span></a>
        <a href="{{ route('projects.index') }}"><span class="ic"><x-icon name="folder-kanban"/></span><span>{{ $ar ? 'المشروعات' : 'Projects' }}</span></a>
        <a href="{{ route('clients.index') }}"><span class="ic"><x-icon name="star"/></span><span>{{ $ar ? 'العملاء' : 'Clients' }}</span></a>
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
    @endphp

    <div class="dx-grid" style="margin-bottom:18px">
        <div class="dx-c4">
            <section class="dx-card dx-hero dx-fill">
                <div class="dx-hero-top">
                    <span class="dx-hero-label">{{ __('agencyos.dashboard_manager.active_tasks') }}</span>
                    <span class="dx-hero-chip"><x-icon name="building-2"/>{{ $ar ? 'المؤسسة' : 'Organisation' }}</span>
                </div>
                <p class="dx-hero-value">{{ $activeTasks }}<span class="dx-hero-unit">{{ $ar ? 'مهمة' : 'tasks' }}</span></p>
                <div class="dx-hero-actions">
                    <a class="dx-btn dx-btn-dark" href="{{ route('tasks.create-form') }}"><x-icon name="plus"/>{{ $ar ? 'مهمة جديدة' : 'New task' }}</a>
                    <a class="dx-btn dx-btn-soft" href="{{ route('reports.index') }}"><x-icon name="bar-chart-3"/>{{ $ar ? 'التقارير' : 'Reports' }}</a>
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
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_manager.waiting_assignment') }}</span>
                        <span class="dx-stat-ic"><x-icon name="clock"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $waitingAssignment }}</p>
                    <p class="dx-stat-foot">{{ $ar ? 'لم تُسند لأحد بعد' : 'Not yet assigned to anyone' }}</p>
                </article>
                <article class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_manager.overdue_steps') }}</span>
                        <span class="dx-stat-ic"><x-icon name="alert-triangle"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $overdueStepsCount }}</p>
                </article>
                <a href="{{ route('projects.index') }}" class="dx-stat">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_manager.active_projects') }}</span>
                        <span class="dx-stat-ic"><x-icon name="folder-kanban"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $activeProjectsCount }}</p>
                </a>
                <a class="dx-stat" href="{{ route('tasks.index', ['status' => \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW]) }}">
                    <div class="dx-stat-top">
                        <span class="dx-stat-label">{{ __('agencyos.dashboard_manager.your_review') }}</span>
                        <span class="dx-stat-ic"><x-icon name="search"/></span>
                    </div>
                    <p class="dx-stat-value">{{ $awaitingManagerReview->count() }}</p>
                </a>
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

    @php
        $rows = collect()
            ->concat($overdueSteps->map(fn ($s) => ['title' => $s->task->title ?? '—', 'dept' => $s->department->name ?? '—', 'status' => 'overdue']))
            ->concat($onHoldTasks->map(fn ($t) => ['title' => $t->title, 'dept' => $t->currentStep?->department?->name ?? '—', 'status' => 'hold']))
            ->concat($awaitingManagerReview->map(fn ($s) => ['title' => $s->task->title ?? '—', 'dept' => $s->department->name ?? '—', 'status' => 'review']))
            ->concat($redirectedAwaitingAssignment->map(fn ($s) => ['title' => $s->task->title ?? '—', 'dept' => $s->department->name ?? '—', 'status' => 'changes']));
        $pillClasses = ['overdue' => 'is-danger', 'hold' => 'is-muted', 'review' => 'is-accent', 'changes' => 'is-warning'];
        $pillLabels = [
            'overdue' => __('agencyos.dashboard_manager.overdue'),
            'hold' => __('agencyos.dashboard_manager.on_hold'),
            'review' => __('agencyos.dashboard_manager.your_review'),
            'changes' => __('agencyos.dashboard_manager.needs_reassignment'),
        ];
        $attentionSearchId = 'dxs-'.\Illuminate\Support\Str::random(8);
    @endphp
    <div class="dx-grid" style="margin-bottom:18px">
        <div class="dx-c8">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div>
                        <h2>{{ __('agencyos.dashboard_manager.needs_attention') }}</h2>
                        <p>{{ $ar ? 'متأخرة، بانتظارك، مُحوَّلة، أو مُعلَّقة' : 'Overdue, awaiting you, redirected, and on hold' }}</p>
                    </div>
                    <div class="dx-toolbar">
                        <label class="dx-search" for="{{ $attentionSearchId }}">
                            <x-icon name="search"/>
                            <input type="search" id="{{ $attentionSearchId }}" data-dx-target="dx-mgr-attention" placeholder="{{ $ar ? 'البحث عن مهمة...' : 'Search tasks...' }}" aria-label="{{ $ar ? 'البحث عن مهمة...' : 'Search tasks...' }}" autocomplete="off">
                        </label>
                    </div>
                </div>
                <div class="dx-table-wrap">
                    <table class="dx-table" id="dx-mgr-attention">
                        <thead>
                            <tr>
                                <th>{{ $ar ? 'المهمة' : 'Task' }}</th>
                                <th>{{ $ar ? 'الحالة' : 'Status' }}</th>
                                <th>{{ $ar ? 'القسم' : 'Department' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rows as $row)
                                <tr>
                                    <td class="dx-td-main">{{ $row['title'] }}</td>
                                    <td><span class="dx-pill {{ $pillClasses[$row['status']] }}"><i></i>{{ $pillLabels[$row['status']] }}</span></td>
                                    <td>{{ $row['dept'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="dx-empty-cell">{{ __('agencyos.dashboard_manager.empty') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <script>
        (function () {
            var input = document.getElementById({{ \Illuminate\Support\Js::from($attentionSearchId) }});
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
                    <div><h2>{{ __('agencyos.dashboard_manager.upcoming_deadlines') }}</h2></div>
                </div>
                <div class="dx-list">
                    @forelse($upcomingDeadlines as $step)
                        <div class="dx-list-item">
                            <span class="dx-row-ic"><x-icon name="calendar"/></span>
                            <div class="dx-list-main">
                                <a class="dx-list-title" href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a>
                                <span class="dx-list-sub">{{ $step->department->name ?? '—' }}</span>
                            </div>
                            <span class="dx-list-end">{{ $step->current_due_at->translatedFormat('M d') }}</span>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_manager.empty') }}</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="dx-grid">
        <div class="dx-c3">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_manager.department_on_time_rate') }}</h2></div>
                </div>
                <div class="dx-card-body">
                    @forelse($departmentScores as $snapshot)
                        <div class="dx-kv">
                            <span>{{ $snapshot->department->name ?? '—' }}</span>
                            <b>{{ $snapshot->displayScore() }}</b>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_manager.empty') }}</div>
                    @endforelse
                </div>
                <a class="dx-more" href="{{ route('reports.index') }}">{{ __('agencyos.performance.view_full') }}<x-icon name="arrow-right"/></a>
            </section>
        </div>

        <div class="dx-c3">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_manager.recent_transfers') }}</h2></div>
                </div>
                <div class="dx-list">
                    @forelse($recentTransfers as $redirect)
                        <div class="dx-list-item">
                            <span class="dx-row-ic"><x-icon name="shuffle"/></span>
                            <div class="dx-list-main">
                                <span class="dx-list-title">{{ $redirect->task->title ?? '—' }}</span>
                                <span class="dx-list-sub">{{ $redirect->fromStep->department->name ?? '—' }} → {{ $redirect->toDepartment->name ?? '—' }}</span>
                            </div>
                            <span class="dx-list-end">{{ $redirect->created_at->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_manager.empty') }}</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="dx-c3">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_manager.active_temp_tls') }}</h2></div>
                </div>
                <div class="dx-card-body">
                    @forelse($activeTempTls as $assignment)
                        <div class="dx-kv">
                            <span>{{ $assignment->department->name ?? '—' }}</span>
                            <b>{{ $assignment->user->full_name ?? '—' }}</b>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_manager.empty') }}</div>
                    @endforelse
                </div>
                <a class="dx-more" href="{{ route('temporary-leadership.index') }}">{{ $ar ? 'عرض الكل' : 'View all' }}<x-icon name="arrow-right"/></a>
            </section>
        </div>

        <div class="dx-c3">
            <section class="dx-card dx-fill">
                <div class="dx-card-head has-line">
                    <div><h2>{{ __('agencyos.dashboard_manager.active_projects_list') }}</h2></div>
                </div>
                <div class="dx-list">
                    @forelse($activeProjects as $project)
                        <div class="dx-list-item">
                            <span class="dx-row-ic"><x-icon name="folder-kanban"/></span>
                            <div class="dx-list-main">
                                <a class="dx-list-title" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                                <span class="dx-list-sub">{{ $project->client->name ?? '—' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="dx-empty">{{ __('agencyos.dashboard_manager.empty') }}</div>
                    @endforelse
                </div>
                <a class="dx-more" href="{{ route('projects.index') }}">{{ $ar ? 'عرض الكل' : 'View all' }}<x-icon name="arrow-right"/></a>
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
                <span class="mini-stat-label">{{ __('agencyos.dashboard_manager.waiting_assignment') }}</span>
                <span class="mini-stat-value">{{ $waitingAssignment }}</span>
            </div>
            <div class="mini-stat is-accent">
                <span class="mini-stat-label">{{ __('agencyos.dashboard_manager.overdue') }}</span>
                <span class="mini-stat-value">{{ $overdueStepsCount }}</span>
            </div>
            <div class="mini-stat">
                <span class="mini-stat-label">{{ __('agencyos.dashboard_manager.active_projects') }}</span>
                <span class="mini-stat-value">{{ $activeProjectsCount }}</span>
            </div>
        </div>

        <div class="grid-2" style="align-items:start">
            <div>
                <div class="card" style="margin-bottom:18px">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.needs_attention') }}</h2></div>
                    <div class="table-wrap">
                        <table>
                            <tbody>
                            @forelse($overdueSteps as $step)
                                <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                    <td><span class="badge b-overdue">{{ __('agencyos.dashboard_manager.overdue') }}</span></td>
                                    <td class="small muted">{{ $step->department->name ?? '—' }}</td></tr>
                            @empty
                            @endforelse
                            @forelse($onHoldTasks as $task)
                                <tr><td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                                    <td><span class="badge b-hold">{{ __('agencyos.dashboard_manager.on_hold') }}</span></td>
                                    <td class="small muted">{{ $task->currentStep?->department?->name ?? '—' }}</td></tr>
                            @empty
                            @endforelse
                            @forelse($awaitingManagerReview as $step)
                                <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                    <td><span class="badge b-review">{{ __('agencyos.dashboard_manager.your_review') }}</span></td>
                                    <td class="small muted">{{ $step->department->name ?? '—' }}</td></tr>
                            @empty
                            @endforelse
                            @forelse($redirectedAwaitingAssignment as $step)
                                <tr><td><a href="{{ route('tasks.show', $step->task_id) }}">{{ $step->task->title ?? '—' }}</a></td>
                                    <td><span class="badge b-changes">{{ __('agencyos.dashboard_manager.needs_reassignment') }}</span></td>
                                    <td class="small muted">{{ $step->department->name ?? '—' }}</td></tr>
                            @empty
                            @endforelse
                            @if($overdueSteps->isEmpty() && $onHoldTasks->isEmpty() && $awaitingManagerReview->isEmpty() && $redirectedAwaitingAssignment->isEmpty())
                                <tr><td class="muted" style="text-align:center;padding:16px">{{ __('agencyos.dashboard_manager.empty') }}</td></tr>
                            @endif
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.department_on_time_rate') }}</h2></div>
                    <div class="card-body">
                        @forelse($departmentScores as $snapshot)
                            <div class="kv-row"><span>{{ $snapshot->department->name ?? '—' }}</span><b>{{ $snapshot->displayScore() }}</b></div>
                        @empty
                            <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                        @endforelse
                        <a class="small" href="{{ route('reports.index') }}">{{ __('agencyos.performance.view_full') }} →</a>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" style="margin-bottom:18px">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.active_temp_tls') }}</h2></div>
                    <div class="card-body">
                        @forelse($activeTempTls as $assignment)
                            <div class="kv-row"><span>{{ $assignment->department->name ?? '—' }}</span><b>{{ $assignment->user->full_name ?? '—' }}</b></div>
                        @empty
                            <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                        @endforelse
                    </div>
                </div>

                <div class="card">
                    <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.active_projects_list') }}</h2></div>
                    <div class="card-body">
                        @forelse($activeProjects as $project)
                            <div class="kv-row"><span><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></span><b class="small muted">{{ $project->client->name ?? '—' }}</b></div>
                        @empty
                            <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:18px">
            <div class="card-head"><h2>{{ __('agencyos.dashboard_manager.upcoming_deadlines') }}</h2></div>
            <div class="card-body" style="padding:8px 16px">
                @forelse($upcomingDeadlines->take(3) as $step)
                    <div class="kv-row"><span>{{ $step->task->title ?? '—' }}</span><b class="mono small">{{ $step->current_due_at?->translatedFormat('M d') }}</b></div>
                @empty
                    <span class="muted small">{{ __('agencyos.dashboard_manager.empty') }}</span>
                @endforelse
            </div>
        </div>

        <div class="kpi-grid" style="margin-top:18px">
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.active_tasks') }}</div><div class="k-value">{{ $activeTasks }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.overdue_steps') }}</div><div class="k-value">{{ $overdueStepsCount }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.waiting_assignment') }}</div><div class="k-value">{{ $waitingAssignment }}</div></article>
            <article class="kpi"><div class="k-label">{{ __('agencyos.dashboard_manager.active_projects') }}</div><div class="k-value">{{ $activeProjectsCount }}</div></article>
        </div>
    </div>
</main>
@endsection
