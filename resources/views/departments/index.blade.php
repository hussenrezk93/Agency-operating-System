@extends('layouts.app')
@section('title', __('agencyos.departments.index.title'))
@section('page', 'departments')
@section('page_header')
    <div class="page-head">
        <div><h1>{{ __('agencyos.departments.index.title') }}</h1></div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('departments.create-form') }}">＋ {{ __('agencyos.departments.index.new_department') }}</a>
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
                    <th>{{ __('agencyos.departments.index.column_name') }}</th>
                    <th>{{ __('agencyos.departments.index.column_leader') }}</th>
                    <th>{{ __('agencyos.departments.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($departments as $department)
                    <tr>
                        <td><span class="dx-td-main">{{ $department->name }}</span></td>
                        <td>{{ $department->primaryLeader()?->full_name ?? '—' }}</td>
                        <td>
                            @if($department->is_active)
                                <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.departments.status.active')]"/>
                            @else
                                <x-dx-pill :badge="['class' => 'b-neutral', 'label' => __('agencyos.departments.status.inactive')]"/>
                            @endif
                        </td>
                        <td class="dx-td-end" style="white-space:nowrap">
                            @can('update', $department)
                                <a class="btn btn-sm btn-outline" href="{{ route('departments.edit-form', $department) }}">{{ __('agencyos.departments.index.edit') }}</a>
                            @endcan
                            @if($department->is_active)
                                @can('deactivate', $department)
                                    <form method="POST" action="{{ route('departments.deactivate', $department) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-danger-outline">{{ __('agencyos.departments.index.deactivate') }}</button>
                                    </form>
                                @endcan
                            @elseif($department->primaryLeader() === null)
                                @can('update', $department)
                                    <a class="btn btn-sm btn-outline" href="{{ route('departments.assign-leader-form', $department) }}">{{ __('agencyos.departments.index.assign_leader') }}</a>
                                @endcan
                            @else
                                @can('reactivate', $department)
                                    <form method="POST" action="{{ route('departments.reactivate', $department) }}" style="display:inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline">{{ __('agencyos.departments.index.reactivate') }}</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="dx-empty-cell">{{ __('agencyos.departments.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
