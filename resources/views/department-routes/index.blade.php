@extends('layouts.app')
@section('title', __('agencyos.routing.index.title'))
@section('page', 'routing')
@section('page_header')
    <div class="page-head"><div>
        <h1>{{ __('agencyos.routing.index.title') }}</h1>
        <div class="page-sub">{{ __('agencyos.routing.index.subtitle') }}</div>
    </div></div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    <div class="dx-card">
        <div class="dx-table-wrap">
            <table class="matrix">
                <thead>
                <tr>
                    <th>{{ __('agencyos.routing.index.from_to') }}</th>
                    @foreach($departments as $to)
                        <th>{{ $to->name }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($departments as $from)
                    <tr>
                        <th>{{ $from->name }}</th>
                        @foreach($departments as $to)
                            @if($from->id === $to->id)
                                <td class="dash" style="text-align:center">—</td>
                            @else
                                @php($isAllowed = $allowed[$from->id][$to->id] ?? false)
                                <td style="text-align:center">
                                    <form method="POST" action="{{ route('department-routes.upsert') }}">
                                        @csrf
                                        <input type="hidden" name="from_department_id" value="{{ $from->id }}">
                                        <input type="hidden" name="to_department_id" value="{{ $to->id }}">
                                        <input type="hidden" name="is_allowed" value="{{ $isAllowed ? '0' : '1' }}">
                                        <button type="submit" class="btn-ghost" style="border:0;background:none;cursor:pointer;font-size:16px;color:{{ $isAllowed ? 'var(--color-success)' : 'var(--color-text-muted)' }}" title="{{ $from->name }} → {{ $to->name }}">
                                            {{ $isAllowed ? '✓' : '✗' }}
                                        </button>
                                    </form>
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
