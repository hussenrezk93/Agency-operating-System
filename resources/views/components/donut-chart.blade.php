@props(['segments', 'centerLabel' => null, 'centerValue' => null])

@php
    // A multi-segment CSS conic-gradient donut — cumulative percentage stops computed
    // here, purely presentational. $segments is always a plain
    // [{'label'=>string,'count'=>int,'color'=>css-color}] list from the controller.
    $total = collect($segments)->sum('count');
    $cursor = 0;
    $stops = [];
    foreach ($segments as $segment) {
        $pct = $total > 0 ? $segment['count'] / $total * 100 : 0;
        $stops[] = sprintf('%s %.3f%% %.3f%%', $segment['color'], $cursor, $cursor + $pct);
        $cursor += $pct;
    }
    $gradient = $total > 0 ? implode(', ', $stops) : 'var(--color-border) 0% 100%';
@endphp
<div class="donut-chart">
    <div class="donut-ring" style="background:conic-gradient({{ $gradient }})">
        <div class="donut-hole">
            @if($centerValue !== null)<div class="donut-value">{{ $centerValue }}</div>@endif
            @if($centerLabel)<div class="donut-caption">{{ $centerLabel }}</div>@endif
        </div>
    </div>
    <div class="ring-legend">
        @foreach($segments as $segment)
            <div class="ring-legend-row">
                <span class="rdot" style="background:{{ $segment['color'] }}"></span>
                <span class="rlabel">{{ $segment['label'] }}</span>
                <span class="rvalue">{{ $segment['count'] }}</span>
            </div>
        @endforeach
    </div>
</div>
