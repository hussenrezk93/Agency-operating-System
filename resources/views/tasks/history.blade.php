@extends('layouts.app')
@section('title', __('agencyos.tasks.show.timeline'))
@section('page', 'tasks')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <div class="small muted mono">{{ $task->task_code }}</div>
            <h1>{{ $task->title }}</h1>
        </div>
        <div class="page-actions"><a class="small" href="{{ route('tasks.show', $task) }}">{{ __('agencyos.tasks.show.back_to_tasks') }}</a></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>{{ __('agencyos.tasks.show.timeline') }}</h2></div>
        <div class="card-body">
            <div class="timeline">
                @foreach($history->reverse() as $event)
                    <div class="t-item">
                        <div class="t-title">{{ __('agencyos.tasks.event.'.$event->event_type->value) }}</div>
                        <div class="t-meta">
                            {{ $event->changedBy?->full_name }}
                            @if($event->department) &middot; {{ $event->department->name }}@endif
                            &middot; <span class="mono">{{ $event->created_at->format('Y-m-d H:i') }}</span>
                        </div>
                        @if($event->reason)<div class="t-body">{{ $event->reason }}</div>@endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</main>
@endsection
