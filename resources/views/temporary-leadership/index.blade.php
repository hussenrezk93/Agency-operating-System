@extends('layouts.app')
@section('title', __('agencyos.temporary_leadership.index.title'))
@section('page', 'temporary-tl')
@section('content')
<main class="page">
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.temporary_leadership.index.title') }}</h1>
            <div class="page-sub">{{ __('agencyos.temporary_leadership.index.subtitle') }}</div>
        </div>
        <div class="page-actions">
            <a class="btn btn-primary" href="{{ route('temporary-leadership.create-form') }}">＋ {{ __('agencyos.temporary_leadership.index.new_delegation') }}</a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>{{ __('agencyos.temporary_leadership.index.column_department') }}</th>
                    <th>{{ __('agencyos.temporary_leadership.index.column_temp_leader') }}</th>
                    <th>{{ __('agencyos.temporary_leadership.index.column_period') }}</th>
                    <th>{{ __('agencyos.temporary_leadership.index.column_status') }}</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($assignments as $assignment)
                    <tr>
                        <td><b>{{ $assignment->department->name }}</b></td>
                        <td>{{ $assignment->user->full_name }}</td>
                        <td class="mono small">{{ $assignment->start_date }} &rarr; {{ $assignment->end_date }}</td>
                        <td>
                            @if($assignment->is_active)
                                <span class="badge b-approved"><span class="bdot"></span>{{ __('agencyos.temporary_leadership.index.active') }}</span>
                            @else
                                <span class="badge b-neutral">{{ __('agencyos.temporary_leadership.index.ended') }}</span>
                            @endif
                        </td>
                        <td style="text-align:end">
                            @if($assignment->is_active)
                                <form method="POST" action="{{ route('temporary-leadership.end', $assignment) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-danger-outline">{{ __('agencyos.temporary_leadership.index.end_early') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;padding:34px" class="muted">{{ __('agencyos.temporary_leadership.index.empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
@endsection
