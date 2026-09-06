@extends('layouts.app')
@section('title', __('agencyos.projects.index.title'))
@section('page', 'projects')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.projects.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.projects.index.subtitle') }}</div>
        </div>
        @if($canCreate)
            <div class="page-actions">
                <a class="btn btn-primary" href="{{ route('projects.create-form') }}"><x-icon name="plus"/> {{ __('agencyos.projects.index.new_project') }}</a>
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
                    <th>{{ __('agencyos.projects.index.column_name') }}</th>
                    <th>{{ __('agencyos.projects.index.column_client') }}</th>
                    <th>{{ __('agencyos.projects.index.column_departments') }}</th>
                    <th>{{ __('agencyos.projects.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($projects as $project)
                    <tr>
                        <td>
                            <a class="dx-td-main" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            <span class="dx-td-sub">{{ $project->project_code }}</span>
                        </td>
                        <td>{{ $project->client->name }}</td>
                        <td>
                            <span class="route-mini">
                                @foreach($project->departments as $department)
                                    <span class="rn">{{ \Illuminate\Support\Str::substr($department->name, 0, 3) }}</span>
                                @endforeach
                            </span>
                        </td>
                        <td><x-dx-pill :badge="\App\Support\ProjectPresenter::statusBadge($project->status)"/></td>
                        <td class="dx-td-end"><a class="btn btn-sm btn-outline" href="{{ route('projects.show', $project) }}"><x-icon name="eye"/> {{ __('agencyos.projects.index.open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="dx-empty-cell">{{ __('agencyos.projects.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($projects->hasPages())
            <div class="card-foot">{{ $projects->links() }}</div>
        @endif
    </div>
</main>
@endsection
