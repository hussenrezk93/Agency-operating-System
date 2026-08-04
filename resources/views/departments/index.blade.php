@extends('layouts.app')
@section('title', __('agencyos.departments.index.title'))
@section('page', 'departments')
@section('content')
<main class="page">
    <div class="page-head">
        <div><h1>{{ __('agencyos.departments.index.title') }}</h1></div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('departments.create-form') }}">＋ {{ __('agencyos.departments.index.new_department') }}</a>
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
                    <th>{{ __('agencyos.departments.index.column_name') }}</th>
                    <th>{{ __('agencyos.departments.index.column_leader') }}</th>
                    <th>{{ __('agencyos.departments.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($departments as $department)
                    <tr>
                        <td><b>{{ $department->name }}</b></td>
                        <td>{{ $department->primaryLeader()?->full_name ?? '—' }}</td>
                        <td>
                            @if($department->is_active)
                                <span class="badge b-approved"><span class="bdot"></span>{{ __('agencyos.departments.status.active') }}</span>
                            @else
                                <span class="badge b-neutral">{{ __('agencyos.departments.status.inactive') }}</span>
                            @endif
                        </td>
                        <td style="text-align:end;white-space:nowrap">
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
                    <tr><td colspan="4" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.departments.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
