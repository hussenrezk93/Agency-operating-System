@extends('layouts.app')
@section('title', $project->name)
@section('page', 'projects')
@php
    $closed = $project->isClosed();
    $badge = \App\Support\ProjectPresenter::statusBadge($project->status);
@endphp
@section('page_header')
    <div class="page-head">
        <div>
            <div class="small muted mono">{{ $project->project_code }} &middot; {{ $project->client->name }}</div>
            <h1 style="margin:2px 0 6px">{{ $project->name }}</h1>
            <x-dx-pill :badge="$badge"/>
        </div>
        <div class="page-actions"><a class="small" href="{{ route('projects.index') }}">{{ __('agencyos.projects.show.back_to_projects') }}</a></div>
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
        <div class="alert alert-danger" style="margin-bottom:16px"><div>🔒 {{ __('agencyos.projects.show.read_only') }}@if($project->cancelled_reason) &middot; {{ $project->cancelled_reason }}@endif</div></div>
    @endif

    <div class="grid grid-2" style="align-items:start">
        <div>
            <div class="dx-card" style="margin-bottom:18px">
                <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.projects.show.details') }}</h2></div></div>
                <div class="dx-card-body">
                    <p style="margin:0 0 12px">{{ $project->description ?: '—' }}</p>

                    <div class="small muted" style="font-weight:700;margin-bottom:6px">{{ __('agencyos.projects.show.links') }}</div>
                    @forelse($project->links as $link)
                        <a class="btn btn-sm btn-outline" style="margin:0 6px 6px 0" href="{{ $link->url }}" target="_blank" rel="noopener">🔗 {{ $link->label ?: $link->url }}</a>
                    @empty
                        <span class="muted small">—</span>
                    @endforelse
                    @if($canUpdate && ! $closed)
                        <form method="POST" action="{{ route('projects.links.store', $project) }}" class="form-row" style="margin-top:12px;align-items:end">
                            @csrf
                            <div class="field"><label>{{ __('agencyos.tasks.fields.url') }}</label><input type="url" name="url" required></div>
                            <div class="field"><label>{{ __('agencyos.tasks.fields.label') }}</label><input type="text" name="label" maxlength="255"></div>
                            <div class="field span2"><button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.projects.show.add_link') }}</button></div>
                        </form>
                    @endif

                    <div class="small muted" style="font-weight:700;margin:16px 0 6px">{{ __('agencyos.projects.show.departments') }}</div>
                    @foreach($project->departments as $department)
                        <span class="tag" style="margin:0 6px 6px 0">{{ $department->name }}
                            @if($canUpdate && ! $closed)
                                <form method="POST" action="{{ route('projects.departments.destroy', [$project, $department]) }}" style="display:inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-ghost" style="border:0;background:none;cursor:pointer;color:inherit" title="{{ __('agencyos.tasks.actions.cancel_task') }}">✕</button>
                                </form>
                            @endif
                        </span>
                    @endforeach
                    @php
                        $availableDepartments = \App\Models\Department::where('is_active', true)
                            ->whereNotIn('id', $project->departments->pluck('id'))
                            ->orderBy('name')->get();
                    @endphp
                    @if($canUpdate && ! $closed && $availableDepartments->isNotEmpty())
                        <form method="POST" action="{{ route('projects.departments.store', $project) }}" class="form-row" style="margin-top:12px;align-items:end">
                            @csrf
                            <div class="field span2">
                                <label>{{ __('agencyos.projects.show.add_department') }}</label>
                                <x-form-select name="department_id" required placeholder="—"
                                    :options="$availableDepartments->pluck('name', 'id')"/>
                            </div>
                            <div class="field"><button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.projects.show.add_department') }}</button></div>
                        </form>
                    @endif

                    <div style="margin-top:16px">
                        <div class="dx-kv"><span>{{ __('agencyos.projects.show.started') }}</span><b class="mono">{{ $project->started_at->format('Y-m-d') }}</b></div>
                        @if($project->completed_at)
                            <div class="dx-kv"><span>{{ __('agencyos.projects.show.closed_at') }}</span><b class="mono">{{ $project->completed_at->format('Y-m-d') }}</b></div>
                            <div class="dx-kv"><span>{{ __('agencyos.projects.show.completed_by') }}</span><b>{{ $project->completedBy?->full_name }}</b></div>
                        @endif
                        @if($project->cancelled_at)
                            <div class="dx-kv"><span>{{ __('agencyos.projects.show.closed_at') }}</span><b class="mono">{{ $project->cancelled_at->format('Y-m-d') }}</b></div>
                            <div class="dx-kv"><span>{{ __('agencyos.projects.show.cancelled_by') }}</span><b>{{ $project->cancelledBy?->full_name }}</b></div>
                            <div class="dx-kv"><span>{{ __('agencyos.projects.show.reason') }}</span><b>{{ $project->cancelled_reason }}</b></div>
                        @endif
                        <div class="dx-kv"><span>{{ __('agencyos.projects.show.created_by') }}</span><b>{{ $project->creator?->full_name }}</b></div>
                    </div>
                </div>
            </div>

            <div class="dx-card">
                <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.projects.show.members') }} ({{ $members->count() }})</h2></div></div>
                <div class="dx-card-body">
                    @forelse($members as $member)
                        <div class="dx-kv"><span>{{ $member['user']->full_name }}</span><span class="small muted">{{ __('agencyos.projects.member_source.'.$member['source']->value) }}</span></div>
                    @empty
                        <span class="muted small">{{ __('agencyos.projects.show.no_members') }}</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div>
            @if(! $closed && ($canComplete || $canCancel || $canHold || $canResume))
                <div class="dx-card" style="margin-bottom:18px">
                    <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.projects.show.actions') }}</h2></div></div>
                    <div class="dx-card-body">
                        @if($canComplete)
                            <form method="POST" action="{{ route('projects.complete', $project) }}" style="margin-bottom:12px">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.projects.show.complete') }}</button>
                            </form>
                        @endif
                        @if($canHold)
                            <form method="POST" action="{{ route('projects.hold', $project) }}" style="margin-bottom:12px">
                                @csrf
                                <div class="field"><label class="req">{{ __('agencyos.projects.show.reason') }}</label><textarea name="reason" required></textarea></div>
                                <button type="submit" class="btn btn-outline btn-sm">{{ __('agencyos.projects.show.hold') }}</button>
                            </form>
                        @endif
                        @if($canResume)
                            <form method="POST" action="{{ route('projects.resume', $project) }}" style="margin-bottom:12px">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.projects.show.resume') }}</button>
                            </form>
                        @endif
                        @if($canCancel)
                            <form method="POST" action="{{ route('projects.cancel', $project) }}">
                                @csrf
                                <div class="field @error('reason') bad @enderror">
                                    <label class="req">{{ __('agencyos.projects.show.reason') }}</label>
                                    <textarea name="reason" required></textarea>
                                    @error('reason')<div class="err">{{ $message }}</div>@enderror
                                </div>
                                <button type="submit" class="btn btn-danger-outline btn-sm">{{ __('agencyos.projects.show.cancel') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            <div class="dx-card" style="margin-bottom:18px">
                <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.projects.show.whatsapp') }}</h2></div></div>
                <div class="dx-card-body">
                    @if($currentLink && $currentLink->group_url)
                        <div class="dx-kv"><span>{{ __('agencyos.projects.show.current_link') }}</span><b class="mono small" style="word-break:break-all">{{ $currentLink->group_url }}</b></div>
                        <div class="dx-kv"><span>v{{ $currentLink->version_no }}</span><b>{{ $currentLink->group_label }}</b></div>
                        <a class="btn btn-primary btn-sm" style="margin-top:10px" href="{{ $currentLink->group_url }}" target="_blank" rel="noopener">💬 {{ __('agencyos.projects.show.current_link') }}</a>
                    @else
                        <span class="muted small">{{ __('agencyos.projects.show.no_link') }}</span>
                    @endif

                    @if($canUpdate && ! $closed)
                        <form method="POST" action="{{ route('projects.whatsapp.store', $project) }}" style="margin-top:14px">
                            @csrf
                            <div class="field @error('url') bad @enderror">
                                <label class="req">{{ __('agencyos.projects.show.current_link') }}</label>
                                <input type="url" name="url" placeholder="https://chat.whatsapp.com/..." required>
                                @error('url')<div class="err">{{ $message }}</div>@enderror
                            </div>
                            <div class="field"><label>{{ __('agencyos.projects.fields.name') }}</label><input type="text" name="label" maxlength="255" value="{{ $project->name }}"></div>
                            <button type="submit" class="btn btn-outline btn-sm">{{ $currentLink ? __('agencyos.projects.show.replace_link') : __('agencyos.projects.show.set_link') }}</button>
                        </form>
                        @if($currentLink && $currentLink->group_url)
                            <form method="POST" action="{{ route('projects.whatsapp.destroy', $project) }}" style="margin-top:10px">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger-outline btn-sm">{{ __('agencyos.projects.show.remove_link') }}</button>
                            </form>
                        @endif
                    @endif

                    <div class="small muted" style="font-weight:700;margin:16px 0 6px">{{ __('agencyos.projects.show.version_history') }}</div>
                    @forelse($linkVersions as $version)
                        <div class="dx-kv">
                            <span><x-dx-pill :badge="['class' => $version->action_type->value === 'removed' ? 'b-cancel' : 'b-approved', 'label' => 'v'.$version->version_no.' · '.$version->action_type->value]"/></span>
                            <span class="small muted">{{ $version->createdBy?->full_name }} &middot; {{ $version->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                    @empty
                        <span class="muted small">{{ __('agencyos.projects.show.no_versions') }}</span>
                    @endforelse
                </div>
            </div>

            <div class="dx-card">
                <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.projects.show.deliveries') }}</h2></div></div>
                <div class="dx-table-wrap">
                    <table class="dx-table">
                        <thead><tr>
                            <th>{{ __('agencyos.projects.show.recipient') }}</th>
                            <th>{{ __('agencyos.projects.show.channel') }}</th>
                            <th>{{ __('agencyos.projects.index.column_status') }}</th>
                        </tr></thead>
                        <tbody>
                        @forelse($deliveries as $delivery)
                            <tr>
                                <td>{{ $delivery->user?->full_name }}</td>
                                <td>{{ __('agencyos.projects.show.'.$delivery->channel->value) }}</td>
                                <td>{{ __('agencyos.projects.delivery_status.'.$delivery->status->value) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="dx-empty-cell">{{ __('agencyos.projects.show.no_deliveries') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection
