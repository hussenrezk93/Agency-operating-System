@extends('layouts.app')
@section('title', $task->title)
@section('page', 'tasks')
@php
    $closed = $task->isClosed();
    $onHold = $task->isOnHold();
    $badge = $onHold
        ? \App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status)
        : ($step ? \App\Support\TaskPresenter::workflowBadge($step->workflow_status) : \App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status));
    $deadlineBadge = $step ? \App\Support\TaskPresenter::deadlineBadge($step->deadline_status) : null;
    $priorityTag = \App\Support\TaskPresenter::priorityTag($task->priority);
@endphp
@section('page_header')
    <div class="page-head">
        <div>
            <div class="small muted mono">{{ $task->task_code }}@if($task->project) &middot; {{ $task->project->name }}@endif</div>
            <h1 style="margin:2px 0 6px">{{ $task->title }}</h1>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <span class="badge {{ $badge['class'] }}"><span class="bdot"></span>{{ $badge['label'] }}</span>
                @if($deadlineBadge)
                    <span class="badge {{ $deadlineBadge['class'] }}"><span class="bdot"></span>{{ $deadlineBadge['label'] }}</span>
                @endif
                <span class="tag {{ $priorityTag['class'] }}">{{ $priorityTag['label'] }}</span>
            </div>
        </div>
        <div class="page-actions">
            @if($canEdit)
                <a class="btn btn-outline btn-sm" href="{{ route('tasks.edit-form', $task) }}"><x-icon name="edit"/> {{ __('agencyos.tasks.show.edit') }}</a>
            @endif
            <a class="small" href="{{ route('tasks.index') }}">{{ __('agencyos.tasks.show.back_to_tasks') }}</a>
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
    @if($closed)
        <div class="alert alert-danger" style="margin-bottom:16px"><div>🔒 {{ __('agencyos.tasks.show.read_only') }}@if($task->cancelled_reason) &middot; {{ $task->cancelled_reason }}@endif</div></div>
    @endif

    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>{{ __('agencyos.tasks.show.route') }}</h2></div>
        <div class="card-body">
            <div class="rail">
                @foreach($task->steps as $s)
                    <div class="rail-step {{ $s->workflow_status->value === 'approved' ? 'done' : ($task->current_step_id === $s->id && ! $closed ? 'current' : '') }}">
                        <div class="rail-dot">{{ $s->workflow_status->value === 'approved' ? '✓' : $s->sequence_no }}</div>
                        <div class="rail-name">{{ $s->department->name }}</div>
                        <div class="small muted">{{ $s->activeAssignment?->assignee?->full_name ?? '—' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-2" style="align-items:start">
        <div>
            <div class="card" style="margin-bottom:18px">
                <div class="card-head"><h2>{{ __('agencyos.tasks.show.details') }}</h2></div>
                <div class="card-body">
                    <p style="margin:0 0 12px">{{ $task->brief }}</p>
                    @foreach($task->referenceLinks as $link)
                        <a class="btn btn-sm btn-outline" style="margin:0 6px 6px 0" href="{{ $link->url }}" target="_blank" rel="noopener">🔗 {{ $link->label ?: $link->url }}</a>
                    @endforeach
                    <div style="margin-top:8px">
                        <div class="kv-row"><span>{{ __('agencyos.tasks.show.department') }}</span><b>{{ $step?->department?->name ?? '—' }}</b></div>
                        <div class="kv-row"><span>{{ __('agencyos.tasks.show.assignee') }}</span><b>{{ $step?->activeAssignment?->assignee?->full_name ?? '—' }}</b></div>
                        <div class="kv-row"><span>{{ __('agencyos.tasks.show.start_date') }}</span><b class="mono">{{ $step?->current_start_date?->format('Y-m-d') ?? '—' }}</b></div>
                        <div class="kv-row"><span>{{ __('agencyos.tasks.show.due_date') }}</span><b class="mono">{{ $step?->current_due_at?->format('Y-m-d H:i') ?? '—' }}</b></div>
                        @if($step && auth()->user()->can('viewFirstSeen', $step))
                            <div class="kv-row"><span>👁 {{ __('agencyos.tasks.show.first_seen') }}</span><b>
                                @if($step->activeAssignment?->first_seen_at)
                                    <span class="seen-badge seen-yes">👁 {{ __('agencyos.tasks.show.seen') }} · <span class="mono">{{ $step->activeAssignment->first_seen_at->format('Y-m-d H:i') }}</span></span>
                                @else
                                    <span class="seen-badge seen-no">◌ {{ __('agencyos.tasks.show.not_seen') }}</span>
                                @endif
                            </b></div>
                        @endif
                        <div class="kv-row"><span>{{ __('agencyos.tasks.show.created_by') }}</span><b>{{ $task->creator?->full_name }} · <span class="mono small">{{ $task->created_at?->format('Y-m-d H:i') }}</span></b></div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom:18px">
                <div class="card-head"><h2>{{ __('agencyos.tasks.show.outputs') }}</h2></div>
                <div class="card-body">
                    @forelse($previousOutputs as $output)
                        <div class="small muted" style="font-weight:700;margin:0 0 6px">{{ __('agencyos.tasks.show.previous_step') }}</div>
                        <a class="btn btn-sm btn-outline" style="margin:0 6px 6px 0" href="{{ $output->url }}" target="_blank" rel="noopener">📎 {{ $output->label ?: $output->url }}</a>
                    @empty
                    @endforelse
                    @if($step)
                        <div class="small muted" style="font-weight:700;margin:12px 0 6px">{{ __('agencyos.tasks.show.current_round') }}</div>
                        @forelse($outputs as $output)
                            <a class="btn btn-sm btn-outline" style="margin:0 6px 6px 0" href="{{ $output->url }}" target="_blank" rel="noopener">📎 {{ $output->label ?: $output->url }}</a>
                        @empty
                            <span class="muted small">{{ __('agencyos.tasks.show.no_outputs') }}</span>
                        @endforelse
                    @endif

                    {{-- addOutput()/submit() both require In Progress or Changes Requested
                         (422 otherwise). The assignee stays the step's "current assignee"
                         all the way through Under Review too (submit() doesn't end the
                         assignment, only approve() does) — canAddOutput/canSubmit alone
                         can't tell the two apart, so the state must be checked here. --}}
                    @php($stepIsEditable = $step !== null && $step->workflow_status->isEditableByAssignee())
                    @if($canAddOutput && $stepIsEditable)
                        <form method="POST" action="{{ route('tasks.steps.outputs.store', $step) }}" class="form-row" style="margin-top:14px;align-items:end">
                            @csrf
                            <div class="field"><label>{{ __('agencyos.tasks.fields.url') }}</label><input type="url" name="url" required></div>
                            <div class="field"><label>{{ __('agencyos.tasks.fields.label') }}</label><input type="text" name="label" maxlength="255"></div>
                            <div class="field span2"><button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.actions.add_output_button') }}</button></div>
                        </form>
                    @endif
                    @if($canSubmit && $stepIsEditable)
                        <form method="POST" action="{{ route('tasks.steps.submit', $step) }}" style="margin-top:10px">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.actions.submit_work') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>{{ __('agencyos.tasks.show.comments') }}</h2></div>
                <div class="card-body">
                    @forelse($comments as $comment)
                        <div class="kv-row" style="display:block">
                            <div class="small muted">{{ $comment->author?->full_name }} · <span class="mono">{{ $comment->created_at->format('Y-m-d H:i') }}</span></div>
                            <div>{{ $comment->body }}</div>
                        </div>
                    @empty
                        <span class="muted small">{{ __('agencyos.tasks.show.no_comments') }}</span>
                    @endforelse

                    @if($canAddComment)
                        <form method="POST" action="{{ route('tasks.steps.comments.store', $step) }}" style="margin-top:14px">
                            @csrf
                            <div class="field @error('body') bad @enderror">
                                <label>{{ __('agencyos.tasks.show.add_comment') }}</label>
                                <textarea name="body" rows="2" required>{{ old('body') }}</textarea>
                                @error('body')<div class="err">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.show.post_comment') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div>
            @if(! $closed && ($canAssign || $canReview || $canTransfer || $canComplete || $canCancel || $canHold || $canResume || $canRedirect))
                <div class="card" style="margin-bottom:18px">
                    <div class="card-head"><h2>{{ __('agencyos.tasks.actions.review') }}</h2></div>
                    <div class="card-body">
                        @if($onHold && $canResume)
                            <form method="POST" action="{{ route('tasks.resume', $task) }}" style="margin-bottom:16px">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.actions.resume_task') }}</button>
                            </form>
                        @endif
                        @unless($onHold)
                        @if($canAssign && $step->workflow_status->value === 'waiting_assignment')
                            <form method="POST" action="{{ route('tasks.steps.assign', $step) }}" style="margin-bottom:16px">
                                @csrf
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.assignee') }}</label>
                                    <x-form-select name="assignee_id" required :options="$assignableUsers->pluck('full_name', 'id')"/>
                                </div>
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.start_date') }}</label><input type="date" name="start_date" required></div>
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.due_date') }}</label><input type="date" name="due_date" required></div>
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.actions.assign') }}</button>
                            </form>
                        {{-- reassign() only accepts In Progress or Changes Requested (422
                             otherwise) — canAssign alone doesn't distinguish that from
                             Under Review/Approved, so the state must be checked here too. --}}
                        @elseif($canAssign && $step->workflow_status->isEditableByAssignee())
                            <form method="POST" action="{{ route('tasks.steps.reassign', $step) }}" style="margin-bottom:16px">
                                @csrf
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.assignee') }}</label>
                                    <x-form-select name="assignee_id" required :options="$assignableUsers->pluck('full_name', 'id')"
                                        :selected="$step->activeAssignment?->assignee_id"/>
                                </div>
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.start_date') }}</label><input type="date" name="start_date" required></div>
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.due_date') }}</label><input type="date" name="due_date" required></div>
                                <div class="field">
                                    <label>{{ __('agencyos.tasks.actions.reason') }}</label>
                                    <textarea name="reason"></textarea>
                                    <div class="hint">{{ __('agencyos.tasks.actions.reason_hint') }}</div>
                                </div>
                                <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.actions.reassign') }}</button>
                            </form>
                        @endif

                        {{-- canReview is deliberately state-agnostic at the policy layer
                             (TaskStepPolicy's own doc comment: state checks belong to the
                             service, not here) — approve()/requestChanges() both reject
                             anything but Under Review with a 422, so the form must only
                             render once the assignee has actually submitted, not merely
                             because the actor is the department's effective leader. --}}
                        @if($canReview && $step->workflow_status->value === 'under_review')
                            <form method="POST" action="{{ route('tasks.steps.review', $step) }}" style="margin-bottom:16px">
                                @csrf
                                <div class="field @error('comment') bad @enderror">
                                    <label>{{ __('agencyos.tasks.actions.comment') }}</label>
                                    <textarea name="comment">{{ old('comment') }}</textarea>
                                    <div class="hint">{{ __('agencyos.tasks.actions.comment_required_note') }}</div>
                                    @error('comment')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <div style="display:flex;gap:8px;flex-wrap:wrap">
                                    <button type="submit" name="decision" value="approved" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.actions.approve') }}</button>
                                    <button type="submit" name="decision" value="changes_requested" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.actions.request_changes') }}</button>
                                </div>
                            </form>
                        @endif

                        {{-- Both actions below require the CURRENT step to already be Approved
                             (TaskWorkflowService::sendToNextDepartment()/completeTask() both
                             reject anything earlier with a 422) — shown only once it is, so
                             they never sit next to the Approve/Request changes buttons above
                             for a step still awaiting review. --}}
                        @if($step->workflow_status->value === 'approved')
                            @if($canTransfer)
                                <form method="POST" action="{{ route('tasks.steps.transfer', $step) }}" style="margin-bottom:16px">
                                    @csrf
                                    <div class="field"><label class="req">{{ __('agencyos.tasks.actions.target_department') }}</label>
                                        <x-form-select name="to_department_id" required :options="$allowedDepartments->pluck('name', 'id')"/>
                                    </div>
                                    <div class="field"><label>{{ __('agencyos.tasks.actions.reason') }}</label><input type="text" name="reason" maxlength="1000"></div>
                                    <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.actions.transfer') }}</button>
                                </form>

                                <form method="POST" action="{{ route('tasks.steps.complete', $step) }}" style="margin-bottom:16px">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.actions.finish') }}</button>
                                </form>
                            @elseif($canComplete)
                                <form method="POST" action="{{ route('tasks.steps.complete', $step) }}" style="margin-bottom:16px">
                                    @csrf
                                    <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.tasks.actions.finish') }}</button>
                                </form>
                            @endif
                        @endif

                        @if($canCancel)
                            <form method="POST" action="{{ route('tasks.cancel', $task) }}">
                                @csrf
                                <div class="field @error('reason') bad @enderror">
                                    <label class="req">{{ __('agencyos.tasks.actions.reason') }}</label>
                                    <textarea name="reason" required></textarea>
                                    @error('reason')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-danger-outline btn-sm">{{ __('agencyos.tasks.actions.cancel_task') }}</button>
                            </form>
                        @endif

                        {{-- redirect() runs the move through the WorkflowStatus transition
                             map, where Approved (and every terminal status) allows no
                             further transitions at all — canRedirect alone doesn't know
                             that, so a step already Approved (awaiting Transfer/Finish
                             instead) must not still offer Redirect. --}}
                        @if($canRedirect && $step !== null && ! $step->workflow_status->isTerminal())
                            <form method="POST" action="{{ route('tasks.redirect', $task) }}" style="margin-top:16px">
                                @csrf
                                <div class="field"><label class="req">{{ __('agencyos.tasks.actions.redirect_target_department') }}</label>
                                    <x-form-select name="department_id" required :options="$redirectDepartments->pluck('name', 'id')"/>
                                </div>
                                <div class="field @error('reason') bad @enderror">
                                    <label class="req">{{ __('agencyos.tasks.actions.reason') }}</label>
                                    <textarea name="reason" required></textarea>
                                    @error('reason')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.actions.redirect_task') }}</button>
                            </form>
                        @endif

                        @if($canHold)
                            <form method="POST" action="{{ route('tasks.hold', $task) }}" style="margin-top:16px">
                                @csrf
                                <div class="field @error('reason') bad @enderror">
                                    <label class="req">{{ __('agencyos.tasks.actions.reason') }}</label>
                                    <textarea name="reason" required></textarea>
                                    @error('reason')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.tasks.actions.hold_task') }}</button>
                            </form>
                        @endif
                        @endunless
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-head">
                    <h2>{{ __('agencyos.tasks.show.timeline') }}</h2>
                    <a class="small" href="{{ route('tasks.history', $task) }}">{{ __('agencyos.tasks.show.full_history') }}</a>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        @foreach($task->history->reverse() as $event)
                            <div class="t-item">
                                <div class="t-title">{{ __('agencyos.tasks.event.'.$event->event_type->value) }}</div>
                                <div class="t-meta">{{ $event->changedBy?->full_name }} · <span class="mono">{{ $event->created_at->format('Y-m-d H:i') }}</span></div>
                                @if($event->reason)<div class="t-body">{{ $event->reason }}</div>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
