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
    <div class="card" style="margin-bottom:18px">
        <div class="card-head"><h2>{{ __('agencyos.search.tasks') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('agencyos.search.task_number') }}</th><th>{{ __('agencyos.search.task_title') }}</th><th>{{ __('agencyos.search.priority') }}</th></tr></thead>
                <tbody>
                @forelse($tasks as $task)
                    @php($priorityTag = \App\Support\TaskPresenter::priorityTag($task->priority))
                    <tr>
                        <td class="mono small">{{ $task->task_code }}</td>
                        <td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                        <td><span class="tag {{ $priorityTag['class'] }}">{{ $priorityTag['label'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted" style="text-align:center;padding:24px">{{ __('agencyos.search.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>{{ __('agencyos.search.projects') }}</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>{{ __('agencyos.search.project_name') }}</th></tr></thead>
                <tbody>
                @forelse($projects as $project)
                    <tr><td><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></td></tr>
                @empty
                    <tr><td class="muted" style="text-align:center;padding:24px">{{ __('agencyos.search.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
