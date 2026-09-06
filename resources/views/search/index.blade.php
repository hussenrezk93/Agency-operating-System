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
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.search.tasks') }}</h2></div></div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead><tr><th>{{ __('agencyos.search.task_number') }}</th><th>{{ __('agencyos.search.task_title') }}</th><th>{{ __('agencyos.search.priority') }}</th></tr></thead>
                <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td>{{ $task->task_code }}</td>
                        <td><a class="dx-td-main" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                        <td><x-dx-pill :badge="\App\Support\TaskPresenter::priorityTag($task->priority)"/></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="dx-empty-cell">{{ __('agencyos.search.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="dx-card">
        <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.search.projects') }}</h2></div></div>
        <div class="dx-table-wrap">
            <table class="dx-table">
                <thead><tr><th>{{ __('agencyos.search.project_name') }}</th></tr></thead>
                <tbody>
                @forelse($projects as $project)
                    <tr><td><a class="dx-td-main" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></td></tr>
                @empty
                    <tr><td class="dx-empty-cell">{{ __('agencyos.search.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
