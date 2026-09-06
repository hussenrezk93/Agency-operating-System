@extends('layouts.app')
@section('title', __('agencyos.users.index.title'))
@section('page', 'users')
@php($isAdmin = auth()->user()->roleCode()->value === 'admin')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.users.index.title') }}</h1>
            <div class="page-sub">{{ $isAdmin ? __('agencyos.users.index.subtitle_admin') : __('agencyos.users.index.subtitle_manager') }}</div>
        </div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('users.create-form') }}"><x-icon name="plus"/> {{ __('agencyos.users.index.new_user') }}</a>
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
    @if(session('temporary_password'))
        <div class="alert alert-danger" style="margin-bottom:16px">
            <div>
                <strong>{{ __('agencyos.users.temp_password.heading') }}</strong>
                <div class="mono" style="font-size:16px;margin:6px 0">{{ session('temporary_password') }}</div>
                <div class="small">{{ __('agencyos.users.temp_password.hint') }}</div>
            </div>
        </div>
    @endif

    <div class="dx-card">
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead>
                <tr>
                    <th>{{ __('agencyos.users.index.column_name') }}</th>
                    <th>{{ __('agencyos.users.index.column_role') }}</th>
                    <th>{{ __('agencyos.users.index.column_department') }}</th>
                    <th>{{ __('agencyos.users.index.column_email') }}</th>
                    <th>{{ __('agencyos.users.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>
                            <span class="dx-td-main">{{ $user->full_name }}</span>
                        </td>
                        <td>{{ __('agencyos.roles.'.$user->role->code) }}</td>
                        <td>{{ $user->department->name ?? '—' }}</td>
                        <td>
                            {{ $user->personal_email }}
                            @if($user->email_verified_at)
                                <x-dx-pill :badge="['class' => 'b-approved', 'label' => '✓']"/>
                            @else
                                <x-dx-pill :badge="['class' => 'b-changes', 'label' => '✉']"/>
                            @endif
                        </td>
                        <td>
                            @php($status = $user->status->value)
                            <x-dx-pill :badge="['class' => $status === 'active' ? 'b-approved' : ($status === 'on_leave' ? 'b-hold' : 'b-cancel'), 'label' => __('agencyos.users.status.'.$status)]"/>
                        </td>
                        <td class="dx-td-end" style="white-space:nowrap">
                            @can('viewPerformance', $user)
                                <a class="dx-icon-btn" href="{{ route('profile.edit', $user) }}" aria-label="{{ __('agencyos.users.index.activity') }}" title="{{ __('agencyos.users.index.activity') }}"><x-icon name="bar-chart-3"/></a>
                            @endcan
                            @can('manage', $user)
                                <a class="dx-icon-btn" href="{{ route('users.edit-form', $user) }}" aria-label="{{ __('agencyos.users.index.edit') }}" title="{{ __('agencyos.users.index.edit') }}"><x-icon name="edit"/></a>
                                <form method="POST" action="{{ route('users.reset-password', $user) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="dx-icon-btn" aria-label="{{ __('agencyos.users.index.reset_password') }}" title="{{ __('agencyos.users.index.reset_password') }}"><x-icon name="key"/></button>
                                </form>
                                @if($status !== 'inactive')
                                    <form method="POST" action="{{ route('users.disable', $user) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="dx-icon-btn" style="color:#DC2626;border-color:var(--color-danger-border)" aria-label="{{ __('agencyos.users.index.disable') }}" title="{{ __('agencyos.users.index.disable') }}"><x-icon name="x"/></button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('users.reactivate', $user) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="dx-icon-btn" aria-label="{{ __('agencyos.users.index.enable') }}" title="{{ __('agencyos.users.index.enable') }}"><x-icon name="check-circle"/></button>
                                    </form>
                                @endif
                            @else
                                <span class="small muted">—</span>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="dx-empty-cell">{{ __('agencyos.users.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="card-foot">{{ $users->links() }}</div>
        @endif
    </div>
</main>
@endsection
