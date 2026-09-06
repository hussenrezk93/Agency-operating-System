@extends('layouts.app')
@section('title', __('agencyos.department_reports.index.title'))
@section('page', 'department-reports')
@section('page_header')
    <div class="page-head">
        <div>
            <h1>{{ __('agencyos.department_reports.index.title') }}</h1>
            <div class="page-sub">{{ $month->translatedFormat('F Y') }}</div>
        </div>
    </div>
    <x-topbar-controls/>
@endsection
@section('content')
<main class="page">
    @php
        $currentMonthKey = $month->format('Y-m');
        $prevMonth = $month->copy()->subMonthNoOverflow()->format('Y-m');
        $nextMonth = $month->copy()->addMonthNoOverflow()->format('Y-m');
        $startOfGrid = $month->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::SUNDAY);
        $endOfGrid = $month->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SATURDAY);
        $dayLabels = [
            __('agencyos.department_reports.index.day_sun'), __('agencyos.department_reports.index.day_mon'),
            __('agencyos.department_reports.index.day_tue'), __('agencyos.department_reports.index.day_wed'),
            __('agencyos.department_reports.index.day_thu'), __('agencyos.department_reports.index.day_fri'),
            __('agencyos.department_reports.index.day_sat'),
        ];
    @endphp
    <div class="dx-card" style="padding:12px 14px;display:flex;flex-direction:row;gap:10px;align-items:center;margin-bottom:14px">
        <a class="btn btn-sm btn-outline" href="{{ route('department-reports.index', ['month' => $prevMonth]) }}">&larr;</a>
        <div style="font-weight:700">{{ $month->translatedFormat('F Y') }}</div>
        <a class="btn btn-sm btn-outline" href="{{ route('department-reports.index', ['month' => $nextMonth]) }}">&rarr;</a>
    </div>

    <div class="dx-card">
        <div class="dx-card-body">
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:8px">
                @foreach($dayLabels as $label)
                    <div class="small muted" style="text-align:center;font-weight:700">{{ $label }}</div>
                @endforeach
            </div>
            <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px">
                @for($day = $startOfGrid->copy(); $day->lte($endOfGrid); $day->addDay())
                    @php
                        $dateKey = $day->toDateString();
                        $inMonth = $day->month === $month->month;
                        $row = $counts->get($dateKey);
                        $isToday = $dateKey === $today;
                    @endphp
                    <a href="{{ route('department-reports.show', $dateKey) }}"
                       style="display:block;min-height:74px;padding:8px;border-radius:10px;border:1px solid var(--dx-line);text-decoration:none;
                              {{ $inMonth ? '' : 'opacity:.35;' }} {{ $isToday ? 'border-color:var(--color-primary);border-width:2px' : '' }}">
                        <div class="mono small" style="font-weight:700;color:var(--dx-ink)">{{ $day->day }}</div>
                        @if($row)
                            <div class="small muted" style="margin-top:4px">{{ (int) $row->submitted }}/{{ (int) $row->total }}</div>
                            @if((int) $row->submitted >= (int) $row->total)
                                <x-dx-pill :badge="['class' => 'b-approved', 'label' => __('agencyos.department_reports.index.all_submitted')]"/>
                            @else
                                <x-dx-pill :badge="['class' => 'b-changes', 'label' => __('agencyos.department_reports.index.pending')]"/>
                            @endif
                        @endif
                    </a>
                @endfor
            </div>
        </div>
    </div>
</main>
@endsection
