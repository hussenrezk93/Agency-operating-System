@extends('layouts.app')
@section('title', __('agencyos.audit_log.title'))
@section('page', 'audit-log')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.audit_log.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.audit_log.subtitle') }}</div>
        </div>
        <div class="page-actions">
            <a class="btn btn-outline" href="{{ route('audit-log.export', request()->query()) }}">{{ __('agencyos.audit_log.export') }}</a>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @php
        $actorOptions = collect($actors)->mapWithKeys(fn ($a) => [(string) $a->id => $a->full_name]);
        $actionOptions = collect($actions)->mapWithKeys(fn ($a) => [$a => $a]);
    @endphp
    <form method="GET" action="{{ route('audit-log.index') }}" class="card glass-dark" style="padding:12px 14px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
        @if(request('actor_id'))<input type="hidden" name="actor_id" value="{{ request('actor_id') }}">@endif
        @if(request('action'))<input type="hidden" name="action" value="{{ request('action') }}">@endif
        <x-filter-select name="actor_id" :options="$actorOptions" :selected="request('actor_id')" :placeholder="__('agencyos.audit_log.filter_actor')"/>
        <x-filter-select name="action" :options="$actionOptions" :selected="request('action')" :placeholder="__('agencyos.audit_log.filter_action')"/>
        <input type="date" name="date" class="select" value="{{ request('date') }}" onchange="this.form.submit()">
        <input type="search" name="q" class="select" style="min-width:200px" placeholder="{{ __('agencyos.audit_log.search_placeholder') }}" value="{{ request('q') }}">
        <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.audit_log.apply') }}</button>
        @if(request()->hasAny(['actor_id', 'action', 'date', 'q']))
            <a class="btn btn-sm btn-outline" href="{{ route('audit-log.index') }}">{{ __('agencyos.audit_log.clear') }}</a>
        @endif
    </form>

    <div class="card glass-dark">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('agencyos.audit_log.column_timestamp') }}</th>
                    <th>{{ __('agencyos.audit_log.column_actor') }}</th>
                    <th>{{ __('agencyos.audit_log.column_action') }}</th>
                    <th>{{ __('agencyos.audit_log.column_entity') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="mono small" dir="ltr">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->actor?->full_name ?? __('agencyos.audit_log.system_actor') }}</td>
                        <td><span class="mono small">{{ $log->action }}</span></td>
                        <td>{{ $log->entity_type }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                        <td style="text-align:end">
                            <details>
                                <summary class="small" style="cursor:pointer;color:var(--color-primary-text)">{{ __('agencyos.audit_log.details') }}</summary>
                                <div class="small muted" style="text-align:start;margin-top:8px;min-width:260px">
                                    <div class="kv-row"><span>{{ __('agencyos.audit_log.column_ip') }}</span><b class="mono">{{ $log->ip_address ?? '—' }}</b></div>
                                    @if(!empty($log->metadata))
                                        <div class="kv-row" style="align-items:flex-start"><span>{{ __('agencyos.audit_log.metadata') }}</span><pre class="mono small" style="white-space:pre-wrap;margin:0">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div>
                                    @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.audit_log.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="card-foot">{{ $logs->links() }}</div>
        @endif
    </div>
</main>
@endsection
