@extends('layouts.app')
@section('title', __('agencyos.search.title'))
@section('page', 'search')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.search.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.search.query_label') }}: "{{ $query }}"</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    <div class="dx-card" style="margin-bottom:18px">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.search.users') }}</h2></div></div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead><tr><th>{{ __('agencyos.search.user_name') }}</th><th>{{ __('agencyos.search.user_department') }}</th><th>{{ __('agencyos.search.user_role') }}</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><a class="dx-td-main" href="{{ route('users.edit-form', $user) }}">{{ $user->full_name }}</a></td>
                        <td>{{ $user->department?->name ?? '—' }}</td>
                        <td>{{ __('agencyos.roles.'.$user->roleCode()->value) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="dx-empty-cell">{{ __('agencyos.search.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dx-card">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.search.departments') }}</h2></div></div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead><tr><th>{{ __('agencyos.search.department_name') }}</th></tr></thead>
                <tbody>
                @forelse($departments as $department)
                    <tr><td><a class="dx-td-main" href="{{ route('departments.edit-form', $department) }}">{{ $department->name }}</a></td></tr>
                @empty
                    <tr><td class="dx-empty-cell">{{ __('agencyos.search.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
