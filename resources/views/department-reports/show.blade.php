@extends('layouts.app')
@section('title', __('agencyos.department_reports.show.title', ['date' => $date]))
@section('page', 'department-reports')
@section('page_header')
    @php
        $parsedDate = \Illuminate\Support\Carbon::parse($date);
    @endphp
    <div class="page-head">
        <div>
            <h1>{{ $parsedDate->translatedFormat('l, j F Y') }}</h1>
        </div>
        {{-- .page-sub is hidden inside the sticky merged header row (agencyos.css —
             ".page-header-row .page-sub{display:none}"), so this navigation lives in
             .page-actions instead, which that same row keeps visible. Icon-only per
             product decision (2026-09) — no prev/next-day links, no visible text. --}}
        <div class="page-actions">
            <a class="dx-icon-btn" href="{{ route('department-reports.index', ['month' => $parsedDate->format('Y-m')]) }}" aria-label="{{ __('agencyos.department_reports.show.back_to_calendar') }}" title="{{ __('agencyos.department_reports.show.back_to_calendar') }}"><x-icon name="calendar"/></a>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="print-only print-letterhead">
        <div class="print-letterhead-band">
            <img src="{{ asset('images/logo.svg') }}" alt="Agency OS">
        </div>
        <div class="print-letterhead-meta">
            <h1>{{ __('agencyos.department_reports.show.print_title') }}</h1>
            <div>{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, j F Y') }}</div>
            <div>{{ __('agencyos.department_reports.show.print_generated', ['date' => now()->translatedFormat('Y-m-d H:i')]) }}</div>
        </div>
    </div>

    @if($canPrint && $reports->isNotEmpty())
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px">
            <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><x-icon name="printer"/> {{ __('agencyos.department_reports.show.print') }}</button>
        </div>
    @endif

    @forelse($reports as $report)
        <div class="dr-card print-page-break">
            <div class="dr-head">
                <span class="dr-head-badge"><x-icon name="building-2"/></span>
                <div>
                    <h2>{{ $report->department->name }}</h2>
                    <p>{{ $report->type->value === 'moderator_handoff' ? __('agencyos.department_reports.show.type_moderator_handoff') : __('agencyos.department_reports.show.type_summary') }}</p>
                </div>
                <div class="dr-head-status">
                    @if($report->isSubmitted())
                        <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.department_reports.show.submitted')]"/>
                        @if($report->is_late)
                            <x-dx-pill :badge="['class' => 'b-overdue', 'label' => __('agencyos.department_reports.show.late')]"/>
                        @endif
                        @if($report->isApproved())
                            <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.department_reports.show.approved')]"/>
                        @endif
                    @else
                        <x-dx-pill :badge="['class' => 'b-changes', 'label' => __('agencyos.department_reports.show.pending')]"/>
                    @endif
                </div>
            </div>

            <div class="dr-meta">
                <div class="dr-meta-item">
                    <span class="dr-meta-ic"><x-icon name="clock"/></span>
                    <div><span>{{ __('agencyos.department_reports.show.meta_submitted_at') }}</span><b>{{ $report->submitted_at?->format('h:i A') ?? '—' }}</b></div>
                </div>
                <div class="dr-meta-item">
                    <span class="dr-meta-ic"><x-icon name="user"/></span>
                    <div><span>{{ __('agencyos.department_reports.show.meta_submitted_by') }}</span><b>{{ $report->submittedBy?->full_name ?? '—' }}</b></div>
                </div>
                @if($report->type->value === 'summary')
                    <div class="dr-meta-item">
                        <span class="dr-meta-ic"><x-icon name="arrow-right"/></span>
                        <div><span>{{ __('agencyos.department_reports.show.tasks_sent_label') }}</span><b>{{ $report->sentTasksCount() }}</b></div>
                    </div>
                    <div class="dr-meta-item">
                        <span class="dr-meta-ic"><x-icon name="download"/></span>
                        <div><span>{{ __('agencyos.department_reports.show.tasks_received_label') }}</span><b>{{ $report->receivedTasksCount() }}</b></div>
                    </div>
                @endif
            </div>

            <div class="dr-body">
                @if($report->type->value === 'summary')
                    <div class="small muted" style="font-weight:700;margin-bottom:8px">{{ __('agencyos.department_reports.show.auto_summary_heading') }}</div>
                    @if(empty($report->auto_summary))
                        <p class="muted small">{{ __('agencyos.department_reports.show.no_activity') }}</p>
                    @else
                        <div class="dx-table-wrap">
                            <table class="dx-table dr-table">
                                <thead>
                                <tr>
                                    <th>{{ __('agencyos.department_reports.show.column_employee') }}</th>
                                    <th>{{ __('agencyos.department_reports.show.column_task') }}</th>
                                    <th>{{ __('agencyos.department_reports.show.column_events') }}</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($report->auto_summary as $row)
                                    <tr>
                                        <td>{{ $row['user_name'] }}</td>
                                        <td>
                                            @if($row['task_id'])
                                                <a class="dx-td-main" href="{{ route('tasks.show', $row['task_id']) }}">{{ $row['task_title'] ?? $row['task_code'] }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($row['event_types'] as $eventType)
                                                <x-dx-pill :badge="['class' => 'b-neutral', 'label' => __('agencyos.tasks.event.'.$eventType)]" style="margin:0 4px 4px 0"/>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if($report->isSubmitted())
                    <div class="dr-box">
                        <span class="dr-box-ic"><x-icon name="edit"/></span>
                        <div>
                            <p class="dr-box-title">{{ __('agencyos.department_reports.show.details_heading') }}</p>
                            <p style="white-space:pre-wrap">{{ $report->details }}</p>
                        </div>
                    </div>
                    @php($commentedTasks = $report->distinctSummaryTasks()->filter(fn ($row) => $report->commentForTask((int) $row['task_id']) !== null))
                    @if($commentedTasks->isNotEmpty())
                        <div class="dr-box">
                            <span class="dr-box-ic"><x-icon name="message-circle"/></span>
                            <div>
                                <p class="dr-box-title">{{ __('agencyos.department_reports.show.task_comments_heading') }}</p>
                                @foreach($commentedTasks as $row)
                                    <p style="margin:0 0 8px">
                                        <a class="dx-td-main" href="{{ route('tasks.show', $row['task_id']) }}">{{ $row['task_title'] ?? $row['task_code'] }}</a>
                                        <span style="white-space:pre-wrap"> — {{ $report->commentForTask((int) $row['task_id']) }}</span>
                                    </p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if($report->external_note)
                        <div class="dr-box">
                            <span class="dr-box-ic"><x-icon name="mail"/></span>
                            <div>
                                <p class="dr-box-title">{{ __('agencyos.department_reports.show.external_note_heading') }}</p>
                                <p style="white-space:pre-wrap">{{ $report->external_note }}</p>
                            </div>
                        </div>
                    @endif
                    <div class="small muted" style="margin-top:10px">{{ __('agencyos.department_reports.show.submitted_by', ['name' => $report->submittedBy?->full_name, 'time' => $report->submitted_at->format('Y-m-d H:i')]) }}</div>
                    @if($report->isApproved())
                        <div class="small muted">{{ __('agencyos.department_reports.show.approved_by', ['name' => $report->approvedBy?->full_name, 'time' => $report->approved_at->format('Y-m-d H:i')]) }}</div>
                    @else
                        @can('approve', $report)
                            <form method="POST" action="{{ route('department-reports.approve', $report) }}" style="margin-top:10px">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.department_reports.show.approve_button') }}</button>
                            </form>
                        @endcan
                    @endif
                    @can('update', $report)
                        <button type="button" class="btn btn-outline btn-sm" style="margin-top:10px"
                                onclick="document.getElementById('dr-edit-form-{{ $report->id }}').style.display='block'; this.style.display='none'">
                            {{ __('agencyos.department_reports.show.edit_button') }}
                        </button>
                        <form method="POST" action="{{ route('department-reports.update', $report) }}" id="dr-edit-form-{{ $report->id }}" style="display:none;margin-top:10px">
                            @csrf
                            @method('PATCH')
                            <div class="field @error('details') bad @enderror">
                                <label>{{ __('agencyos.department_reports.show.details_heading') }}</label>
                                <textarea name="details" rows="4" required>{{ old('details', $report->details) }}</textarea>
                                @error('details')<div class="err">{{ $message }}</div>@enderror
                            </div>
                            @foreach($report->distinctSummaryTasks() as $row)
                                <div class="field @error("task_comments.{$row['task_id']}") bad @enderror">
                                    <label>{{ __('agencyos.department_reports.show.task_comment_for', ['task' => $row['task_title'] ?? $row['task_code']]) }}</label>
                                    <input type="text" name="task_comments[{{ $row['task_id'] }}]" maxlength="1000" value="{{ old("task_comments.{$row['task_id']}", $report->commentForTask((int) $row['task_id'])) }}">
                                    @error("task_comments.{$row['task_id']}")<div class="err">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                            <div class="field @error('external_note') bad @enderror">
                                <label>{{ __('agencyos.department_reports.show.external_note_heading') }}</label>
                                <textarea name="external_note" rows="3" maxlength="2000">{{ old('external_note', $report->external_note) }}</textarea>
                                @error('external_note')<div class="err">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.department_reports.show.save_button') }}</button>
                        </form>
                    @endcan
                @elseif($ownUnsubmitted->contains('id', $report->id))
                    @php($collective = $contributions[$report->id] ?? null)
                    {{-- A collectively-written report (Sales, product decision 2026-09):
                         the Team Leader writes their OWN part like everyone else, in its
                         own form, and the report's text is assembled by the system. --}}
                    @if($collective)
                        @php($mine = $collective['written']->get(auth()->id()))
                        <form method="POST" action="{{ route('department-reports.contributions.store', $report) }}" style="margin-top:18px">
                            @csrf
                            <div class="field @error('body') bad @enderror">
                                <label class="req">{{ __('agencyos.department_reports.contributions.my_part') }}</label>
                                <textarea name="body" rows="4" maxlength="5000" required
                                          placeholder="{{ __('agencyos.department_reports.contributions.placeholder') }}">{{ old('body', $mine?->body) }}</textarea>
                                @error('body')<div class="err">{{ $message }}</div>@enderror
                                <div class="hint">{{ __('agencyos.department_reports.contributions.edit_hint') }}</div>
                            </div>
                            <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.department_reports.contributions.save') }}</button>
                        </form>

                        <div class="dr-box" style="margin-top:18px">
                            <p class="dr-box-title">{{ __('agencyos.department_reports.contributions.team') }}</p>
                            <ul class="dr-contrib-list">
                                @foreach($collective['members'] as $member)
                                    @php($entry = $collective['written']->get($member->id))
                                    <li>
                                        <span class="badge {{ $entry ? 'b-approved' : 'b-changes' }}">
                                            {{ $entry ? __('agencyos.department_reports.contributions.written') : __('agencyos.department_reports.contributions.not_written') }}
                                        </span>
                                        <b>{{ $member->full_name }}</b>
                                        @if($entry)<span class="muted small">{{ $entry->submitted_at?->format('h:i A') }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                            @if($collective['missing']->isEmpty())
                                <p class="small" style="margin-top:8px">{{ __('agencyos.department_reports.contributions.all_in') }}</p>
                            @else
                                <p class="small muted" style="margin-top:8px">{{ __('agencyos.department_reports.contributions.submit_blocked_hint', ['count' => $collective['missing']->count()]) }}</p>
                            @endif
                        </div>
                    @endif

                    <form method="POST" action="{{ route('department-reports.submit', $report) }}" style="margin-top:18px"
                          @if($collective && $collective['missing']->isNotEmpty())
                              onsubmit="return confirm('{{ __('agencyos.department_reports.contributions.submit_confirm', ['count' => $collective['missing']->count()]) }}')"
                          @endif>
                        @csrf
                        @if($collective)
                            {{-- The text is composed by DepartmentReportService from the
                                 members' own parts; this field only satisfies the request
                                 validation, and its value is discarded there. --}}
                            <input type="hidden" name="details" value="-">
                            <div class="dr-box">
                                <p class="dr-box-title">{{ __('agencyos.department_reports.contributions.preview') }}</p>
                                <p style="white-space:pre-wrap">{{ $composedPreview[$report->id] ?? '' }}</p>
                                <p class="hint">{{ __('agencyos.department_reports.contributions.preview_hint') }}</p>
                            </div>
                        @else
                        <div class="field @error('details') bad @enderror">
                            <label class="req">{{ __('agencyos.department_reports.show.details_heading') }}</label>
                            <textarea name="details" rows="4" required>{{ old('details') }}</textarea>
                            @error('details')<div class="err">{{ $message }}</div>@enderror
                        </div>
                        @endif
                        @foreach($report->distinctSummaryTasks() as $row)
                            <div class="field @error("task_comments.{$row['task_id']}") bad @enderror">
                                <label>{{ __('agencyos.department_reports.show.task_comment_for', ['task' => $row['task_title'] ?? $row['task_code']]) }}</label>
                                <input type="text" name="task_comments[{{ $row['task_id'] }}]" maxlength="1000" value="{{ old("task_comments.{$row['task_id']}") }}">
                                @error("task_comments.{$row['task_id']}")<div class="err">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                        <div class="field @error('external_note') bad @enderror">
                            <label>{{ __('agencyos.department_reports.show.external_note_heading') }}</label>
                            <textarea name="external_note" rows="3" maxlength="2000">{{ old('external_note') }}</textarea>
                            @error('external_note')<div class="err">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.department_reports.show.submit_button') }}</button>
                    </form>
                @else
                    <p class="muted small" style="margin-top:18px">{{ __('agencyos.department_reports.show.awaiting_submission') }}</p>
                @endif
            </div>
        </div>
    @empty
        @if($pendingGeneration)
            <div class="dx-card" style="opacity:.55;filter:grayscale(1)">
                <div class="dx-card-head has-line">
                    <div>
                        <h2>{{ __('agencyos.department_reports.show.locked_heading') }}</h2>
                        <p>{{ __('agencyos.department_reports.show.locked_body', ['time' => \Illuminate\Support\Carbon::parse($generationTime)->translatedFormat('g:i A')]) }}</p>
                    </div>
                    <div style="margin-inline-start:auto"><x-icon name="lock"/></div>
                </div>
                @if($isTeamLeader)
                    <div class="dx-card-body">
                        <div class="field" style="margin:0">
                            <label>{{ __('agencyos.department_reports.show.details_heading') }}</label>
                            <textarea rows="4" disabled placeholder="{{ __('agencyos.department_reports.show.locked_placeholder') }}"></textarea>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" disabled style="margin-top:10px;cursor:not-allowed">{{ __('agencyos.department_reports.show.submit_button') }}</button>
                    </div>
                @endif
            </div>
        @else
            <div class="dx-card"><div class="dx-card-body"><span class="muted small">{{ __('agencyos.department_reports.show.empty') }}</span></div></div>
        @endif
    @endforelse
</main>
@endsection
