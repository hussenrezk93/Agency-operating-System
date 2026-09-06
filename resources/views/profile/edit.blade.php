@php
    $activeTab = $isSelf && request('tab') !== 'activity' ? 'account' : 'activity';
@endphp
@extends('layouts.app')
@section('title', $isSelf ? __('agencyos.profile.title') : $user->full_name)
@section('page', 'profile')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ $isSelf ? __('agencyos.profile.title') : $user->full_name }}</h1>
            <div class="page-sub">{{ $isSelf ? __('agencyos.profile.subtitle') : __('agencyos.profile.activity.subtitle_other', ['name' => $user->full_name]) }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    @if($isSelf)
        <div class="dx-card" style="padding:10px 14px;margin-bottom:14px;display:flex;flex-direction:row;gap:8px">
            <a class="btn btn-sm {{ $activeTab === 'account' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('profile.edit') }}">{{ __('agencyos.profile.tabs.account') }}</a>
            <a class="btn btn-sm {{ $activeTab === 'activity' ? 'btn-primary' : 'btn-outline' }}" href="{{ route('profile.edit', ['tab' => 'activity']) }}">{{ __('agencyos.profile.tabs.activity') }}</a>
        </div>
    @endif

    @if($activeTab === 'account')
        <div class="dx-card form-card" style="margin-bottom:16px">
            <div class="form-section">
                <div class="form-section-head"><span class="n">1</span><h2>{{ __('agencyos.profile.avatar.title') }}</h2></div>
                <div class="profile-avatar-row">
                    <span class="avatar lg"><x-avatar :user="$user"/></span>
                    <div>
                        <div class="hint">{{ __('agencyos.profile.avatar.hint') }}</div>
                        @error('avatar')<div class="err">{{ $message }}</div>@enderror
                        <div class="actions">
                            <form method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data" id="avatarForm">
                                @csrf
                                <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/webp" required hidden onchange="document.getElementById('avatarForm').submit()">
                                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('avatarInput').click()">{{ __('agencyos.profile.avatar.upload') }}</button>
                            </form>
                            @if($user->avatar_url)
                                <form method="POST" action="{{ route('profile.avatar.destroy') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.profile.avatar.remove') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('profile.update') }}" class="dx-card form-card">
            @csrf
            @method('PATCH')
            <div class="form-section">
                <div class="form-grid">
                    <div class="field @error('personal_email') bad @enderror">
                        <label class="req">{{ __('agencyos.profile.current_email') }}</label>
                        <input type="email" name="personal_email" value="{{ old('personal_email', $user->personal_email) }}" maxlength="255" required>
                        @error('personal_email')<div class="err">{{ $message }}</div>@enderror
                        @if($user->pending_email)
                            <div class="small muted" style="margin-top:6px">{{ __('agencyos.profile.pending_email', ['email' => $user->pending_email]) }}</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="form-actions-sticky">
                <button type="submit" class="btn btn-primary">{{ __('agencyos.profile.submit') }}</button>
            </div>
        </form>
    @else
        @unless($isSelf)
            <div class="dx-card" style="padding:14px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
                <span class="avatar lg"><x-avatar :user="$user"/></span>
                <div>
                    <div style="font-weight:600">{{ $user->full_name }}</div>
                    <div class="small muted">{{ $user->department?->name ?? '—' }}</div>
                </div>
            </div>
        @endunless

        <div class="print-only print-letterhead">
            <div class="print-letterhead-band">
                <img src="{{ asset('images/logo.svg') }}" alt="Agency OS">
            </div>
            <div class="print-letterhead-meta">
                <h1>{{ __('agencyos.profile.activity.print_title') }}</h1>
                <div>{{ $user->full_name }}@if($user->department) &middot; {{ $user->department->name }}@endif</div>
                <div>{{ __('agencyos.profile.activity.print_generated', ['date' => now()->translatedFormat('Y-m-d')]) }}</div>
            </div>
        </div>

        <div class="dx-card">
            <div class="dx-card-head has-line">
                <div><h2>{{ __('agencyos.profile.activity.title') }}</h2></div>
                <button type="button" class="btn btn-outline btn-sm" style="margin-inline-start:auto" onclick="window.print()"><x-icon name="printer"/> {{ __('agencyos.profile.activity.print') }}</button>
            </div>
            @if($filterDate)
                <div class="print-only" style="padding:0 20px">
                    <div class="small muted">{{ __('agencyos.profile.activity.filtered_for', ['date' => $filterDate]) }}</div>
                </div>
            @endif
            <form method="GET" action="{{ route('profile.edit', $isSelf ? [] : $user) }}" class="print-hide" style="padding:14px 20px 0;display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                <input type="hidden" name="tab" value="activity">
                <div class="field" style="margin:0">
                    <label>{{ __('agencyos.profile.activity.filter_date') }}</label>
                    <input type="date" name="date" value="{{ $filterDate }}">
                </div>
                <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.profile.activity.filter_apply') }}</button>
                @if($filterDate)
                    <a class="btn btn-outline btn-sm" href="{{ route('profile.edit', $isSelf ? ['tab' => 'activity'] : [$user, 'tab' => 'activity']) }}">{{ __('agencyos.profile.activity.filter_clear') }}</a>
                @endif
            </form>
            <div class="dx-table-wrap">
                <table class="dx-table">
                    <thead>
                    <tr>
                        <th>{{ __('agencyos.profile.activity.column_task') }}</th>
                        <th>{{ __('agencyos.profile.activity.column_role') }}</th>
                        <th>{{ __('agencyos.profile.activity.column_department') }}</th>
                        <th>{{ __('agencyos.profile.activity.column_status') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($tasks as $task)
                        <tr>
                            <td>
                                <a class="dx-td-main" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                <span class="dx-td-sub">{{ $task->task_code }}@if($task->project) &middot; {{ $task->project->name }}@endif</span>
                            </td>
                            <td>
                                @if($task->created_by === $user->id)
                                    <x-dx-pill :badge="['class' => 'b-neutral', 'label' => __('agencyos.profile.activity.role_creator')]"/>
                                @else
                                    <x-dx-pill :badge="['class' => 'b-neutral', 'label' => __('agencyos.profile.activity.role_assignee')]"/>
                                @endif
                            </td>
                            <td>{{ $task->currentStep?->department?->name ?? '—' }}</td>
                            <td>
                                @if($task->isOnHold())
                                    <x-dx-pill :badge="\App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status)"/>
                                @elseif($task->currentStep)
                                    <x-dx-pill :badge="\App\Support\TaskPresenter::workflowBadge($task->currentStep->workflow_status, $task->currentStep->activeAssignment?->is_self_assigned)"/>
                                @else
                                    <x-dx-pill :badge="\App\Support\TaskPresenter::lifecycleBadge($task->lifecycle_status)"/>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="dx-empty-cell">{{ __('agencyos.profile.activity.empty') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($tasks->hasPages())
                <div class="card-foot">{{ $tasks->links() }}</div>
            @endif
        </div>
    @endif
</main>
@endsection
