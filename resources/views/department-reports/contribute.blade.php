@extends('layouts.app')
@section('title', __('agencyos.department_reports.contributions.title'))
@section('page', 'my-report')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.department_reports.contributions.title') }}</h1>
            <div class="page-sub">{{ now()->translatedFormat('l, j F Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @if(session('status'))
        <div class="alert alert-success" style="margin-bottom:16px"><div>{{ session('status') }}</div></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:16px"><div>{{ $errors->first() }}</div></div>
    @endif

    @if(! $collects)
        {{-- Only a department that writes collectively has anything here. Everyone else
             is told so plainly rather than shown an empty form they cannot use. --}}
        <div class="dx-card" style="padding:18px">
            <p class="muted">
                @if(! $isCollectiveDepartment)
                    {{ __('agencyos.department_reports.contributions.not_collective') }}
                @elseif($pendingGeneration)
                    {{ __('agencyos.department_reports.contributions.not_generated', ['time' => $generationTime]) }}
                @else
                    {{ __('agencyos.department_reports.contributions.no_report') }}
                @endif
            </p>
        </div>
    @else
        <div class="dx-card" style="margin-bottom:16px">
            <div class="dx-card-head has-line">
                <div>
                    <h2>{{ __('agencyos.department_reports.contributions.my_part') }}</h2>
                    <p class="dx-card-sub">{{ __('agencyos.department_reports.contributions.subtitle') }}</p>
                </div>
                @if($report->isSubmitted())
                    <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.department_reports.show.submitted')]"/>
                @endif
            </div>

            <div style="padding:14px 16px">
                @if($report->isSubmitted())
                    <p class="muted small">{{ __('agencyos.department_reports.contributions.locked') }}</p>
                    @if($mine)
                        <p style="white-space:pre-wrap;margin-top:10px">{{ $mine->body }}</p>
                    @endif
                @else
                    <form method="POST" action="{{ route('department-reports.contributions.store', $report) }}">
                        @csrf
                        <div class="field @error('body') bad @enderror">
                            <textarea name="body" rows="6" maxlength="5000" required
                                      placeholder="{{ __('agencyos.department_reports.contributions.placeholder') }}">{{ old('body', $mine?->body) }}</textarea>
                            @error('body')<div class="err">{{ $message }}</div>@enderror
                            <div class="hint">{{ __('agencyos.department_reports.contributions.edit_hint') }}</div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('agencyos.department_reports.contributions.save') }}</button>
                        @if($mine)
                            <span class="muted small" style="margin-inline-start:10px">
                                {{ __('agencyos.department_reports.contributions.saved_at', ['time' => ($mine->updated_at ?? $mine->submitted_at)->format('h:i A')]) }}
                                @if($mine->updated_at) · {{ __('agencyos.department_reports.contributions.edited') }} @endif
                            </span>
                        @endif
                    </form>
                @endif
            </div>
        </div>

        {{-- Who else has written. Names and a yes/no only — a colleague's own words are
             for the Team Leader and the Manager, not for the whole team to read here. --}}
        <div class="dx-card">
            <div class="dx-card-head has-line"><div><h2>{{ __('agencyos.department_reports.contributions.team') }}</h2></div></div>
            <ul class="dr-contrib-list" style="padding:14px 16px">
                @foreach($status['members'] as $member)
                    @php($entry = $status['written']->get($member->id))
                    <li>
                        <span class="badge {{ $entry ? 'b-approved' : 'b-changes' }}">
                            {{ $entry ? __('agencyos.department_reports.contributions.written') : __('agencyos.department_reports.contributions.not_written') }}
                        </span>
                        <b>{{ $member->full_name }}</b>
                        @if($entry)<span class="muted small">{{ $entry->submitted_at?->format('h:i A') }}</span>@endif
                    </li>
                @endforeach
            </ul>
            <p class="small muted" style="padding:0 16px 14px">
                @if($status['missing']->isEmpty())
                    {{ __('agencyos.department_reports.contributions.all_in') }}
                @else
                    {{ __('agencyos.department_reports.contributions.waiting', ['count' => $status['missing']->count()]) }}
                @endif
            </p>
        </div>
    @endif
</main>
@endsection
