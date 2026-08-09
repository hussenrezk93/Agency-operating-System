@props(['heading' => null])
@php
    // Real server-rendered month navigation via ?month=Y-m — "today" always stays the
    // actual current date for highlighting, independent of which month is displayed.
    $today = now();
    $displayed = request('month') ? \Carbon\Carbon::parse(request('month').'-01') : $today->copy()->startOfMonth();
    $startOfGrid = $displayed->copy()->startOfMonth()->startOfWeek(\Carbon\Carbon::MONDAY);
    $endOfGrid = $displayed->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::MONDAY);
    $days = [];
    for ($cursor = $startOfGrid->copy(); $cursor->lte($endOfGrid); $cursor->addDay()) {
        $days[] = $cursor->copy();
    }
    $prevHref = request()->fullUrlWithQuery(['month' => $displayed->copy()->subMonth()->format('Y-m')]);
    $nextHref = request()->fullUrlWithQuery(['month' => $displayed->copy()->addMonth()->format('Y-m')]);
@endphp
<div class="mini-cal">
    <div class="mini-cal-head">
        @if($heading)<span class="mini-cal-title">{{ $heading }}</span>@endif
        <span class="mini-cal-nav">
            <a href="{{ $prevHref }}" class="mini-cal-arrow mini-cal-prev" aria-label="{{ __('agencyos.common.previous') }}"><x-icon name="chevron-right"/></a>
            <span class="mini-cal-month">{{ $displayed->translatedFormat('M Y') }}</span>
            <a href="{{ $nextHref }}" class="mini-cal-arrow mini-cal-next" aria-label="{{ __('agencyos.common.next') }}"><x-icon name="chevron-right"/></a>
        </span>
    </div>
    <div class="mini-cal-grid">
        @foreach(array_slice($days, 0, 7) as $day)
            <span class="mini-cal-dow">{{ $day->translatedFormat('D') }}</span>
        @endforeach
        @foreach($days as $day)
            <span class="mini-cal-day{{ $day->isSameMonth($displayed) ? '' : ' out' }}{{ $day->isToday() ? ' today' : '' }}">{{ $day->day }}</span>
        @endforeach
    </div>
</div>
