@extends('layouts.app')
@section('title', __('agencyos.clients.index.title'))
@section('page', 'clients')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.clients.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.clients.index.subtitle') }}</div>
        </div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('clients.create-form') }}">＋ {{ __('agencyos.clients.index.new_client') }}</a>
            </div>
        @endif
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('agencyos.clients.index.column_name') }}</th>
                    <th>{{ __('agencyos.clients.index.column_phone') }}</th>
                    <th>{{ __('agencyos.clients.index.column_email') }}</th>
                    <th>{{ __('agencyos.clients.index.column_projects') }}</th>
                    <th>{{ __('agencyos.clients.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($clients as $client)
                    <tr>
                        <td>
                            <b>{{ $client->name }}</b>
                            @if($client->short_description)<div class="small muted">{{ $client->short_description }}</div>@endif
                        </td>
                        <td class="mono small">{{ $client->phone }}</td>
                        <td class="mono small">{{ $client->company_email ?: '—' }}</td>
                        <td>{{ $client->projects_count }}</td>
                        <td>
                            @if($client->status->value === 'active')
                                <span class="badge b-approved"><span class="bdot"></span>{{ __('agencyos.clients.status.active') }}</span>
                            @else
                                <span class="badge b-neutral">{{ __('agencyos.clients.status.inactive') }}</span>
                            @endif
                        </td>
                        <td style="text-align:end;white-space:nowrap">
                            <a class="btn btn-sm btn-outline" href="{{ route('clients.edit-form', $client) }}">{{ __('agencyos.clients.index.edit') }}</a>
                            @if($client->status->value === 'active')
                                <form method="POST" action="{{ route('clients.deactivate', $client) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger-outline">{{ __('agencyos.clients.index.deactivate') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('clients.reactivate', $client) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.clients.index.reactivate') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.clients.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
