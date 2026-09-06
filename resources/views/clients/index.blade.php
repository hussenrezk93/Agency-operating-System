@extends('layouts.app')
@section('title', __('agencyos.clients.index.title'))
@section('page', 'clients')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.clients.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.clients.index.subtitle') }}</div>
        </div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('clients.create-form') }}"><x-icon name="plus"/> {{ __('agencyos.clients.index.new_client') }}</a>
            </div>
        @endif
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    <div class="dx-card">
        <div class="dx-table-wrap">
            <table class="dx-table">
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
                            <span class="dx-td-main">{{ $client->name }}</span>
                            @if($client->short_description)<span class="dx-td-sub">{{ $client->short_description }}</span>@endif
                        </td>
                        <td>{{ $client->phone }}</td>
                        <td>{{ $client->company_email ?: '—' }}</td>
                        <td class="dx-td-num">{{ $client->projects_count }}</td>
                        <td>
                            @if($client->status->value === 'active')
                                <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.clients.status.active')]"/>
                            @else
                                <x-dx-pill :badge="['class' => 'b-neutral', 'label' => __('agencyos.clients.status.inactive')]"/>
                            @endif
                        </td>
                        <td class="dx-td-end" style="white-space:nowrap">
                            @if($canUpdate)
                                <a class="btn btn-sm btn-outline" href="{{ route('clients.edit-form', $client) }}"><x-icon name="edit"/> {{ __('agencyos.clients.index.edit') }}</a>
                            @endif
                            @if($client->status->value === 'active')
                                @if($canDeactivate)
                                    <form method="POST" action="{{ route('clients.deactivate', $client) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger-outline">{{ __('agencyos.clients.index.deactivate') }}</button>
                                    </form>
                                @endif
                            @else
                                @if($canReactivate)
                                    <form method="POST" action="{{ route('clients.reactivate', $client) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.clients.index.reactivate') }}</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="dx-empty-cell">{{ __('agencyos.clients.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($clients->hasPages())
            <div class="card-foot">{{ $clients->links() }}</div>
        @endif
    </div>
</main>
@endsection
