@extends('layouts.app')
@section('title', __('agencyos.dashboard.page_title'))
@section('page', 'dashboard')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ app()->isLocale('ar') ? 'مرحبا' : 'Hi' }} {{ $displayName }} 👋</h1>
            <div class="page-sub">{{ $roleLabel }} · Africa/Cairo · {{ now()->translatedFormat('l d M Y') }}</div>
        </div>
        <div class="page-actions"><a class="btn btn-primary" href="{{ route('approved-ui', ['screen' => $user->roleCode()->value.'-dashboard.html']) }}">{{ app()->isLocale('ar') ? 'فتح المعاينة الكاملة' : 'Open full UI preview' }}</a></div>
    </div>

    <div class="alert alert-brand preview-note"><div>{{ app()->isLocale('ar') ? 'تمت إعادة الواجهة إلى التصميم البرتقالي والأبيض المعتمد. الصفحات الموجودة في Prototype هي مرجع التصميم، ويتم ربطها بالباك إند مرحلة بمرحلة.' : 'The approved orange-and-white UI has been restored. Prototype screens are the visual source of truth and will be connected to the backend phase by phase.' }}</div></div>

    @if(session('status'))<div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>@endif

    <div class="kpi-grid">
        @foreach(($demo['stats'] ?? []) as $stat)
            <article class="kpi"><div class="k-label">{{ $stat['label'] }}</div><div class="k-value">{{ $stat['value'] }}</div><div class="k-hint">{{ $stat['hint'] }}</div></article>
        @endforeach
    </div>

    <div class="card">
        <div class="card-head"><h2>{{ __('agencyos.dashboard.recent_tasks') }}</h2><div class="page-actions"><a class="small" href="{{ route('approved-ui', ['screen' => 'tasks.html']) }}">{{ app()->isLocale('ar') ? 'عرض الكل ←' : 'View all →' }}</a></div></div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>{{ __('agencyos.dashboard.task_number') }}</th><th>{{ __('agencyos.dashboard.task') }}</th><th>{{ __('agencyos.dashboard.department') }}</th><th>{{ __('agencyos.dashboard.status') }}</th><th>{{ __('agencyos.dashboard.priority') }}</th></tr></thead>
                <tbody>
                @foreach(($demo['tasks'] ?? []) as $task)
                    <tr><td class="mono" dir="ltr">{{ $task['number'] }}</td><td><strong>{{ $task['title'] }}</strong></td><td>{{ $task['department'] }}</td><td><span class="badge {{ $task['tone']==='green'?'b-approved':($task['tone']==='orange'?'b-progress':'b-changes') }}">{{ $task['status'] }}</span></td><td>{{ $task['priority'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
