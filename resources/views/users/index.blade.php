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

    <div class="card glass-dark">
        <div class="table-wrap">
            <table>
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
                            <b>{{ $user->full_name }}</b>
                        </td>
                        <td>{{ __('agencyos.roles.'.$user->role->code) }}</td>
                        <td>{{ $user->department->name ?? '—' }}</td>
                        <td class="mono small">
                            {{ $user->personal_email }}
                            @if($user->email_verified_at)
                                <span class="badge b-approved">✓</span>
                            @else
                                <span class="badge b-changes">✉</span>
                            @endif
                        </td>
                        <td>
                            @php($status = $user->status->value)
                            <span class="badge {{ $status === 'active' ? 'b-approved' : ($status === 'on_leave' ? 'b-hold' : 'b-cancel') }}">
                                <span class="bdot"></span>{{ __('agencyos.users.status.'.$status) }}
                            </span>
                        </td>
                        <td style="text-align:end;white-space:nowrap">
                            <a class="btn btn-sm btn-outline" href="{{ route('users.edit-form', $user) }}"><x-icon name="edit"/> {{ __('agencyos.users.index.edit') }}</a>
                            <form method="POST" action="{{ route('users.reset-password', $user) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline">🔑</button>
                            </form>
                            @if($status !== 'inactive')
                                <form method="POST" action="{{ route('users.disable', $user) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger-outline">{{ __('agencyos.users.index.disable') }}</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('users.reactivate', $user) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.users.index.enable') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.users.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
