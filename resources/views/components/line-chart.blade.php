@props(['series'])

@php
    // Smooth curve via Catmull-Rom → cubic-Bezier conversion (standard, deterministic —
    // not an approximation of the real values, just how the straight-line points are
    // joined). Geometry only; $series is always a plain [{'date'=>Carbon,'count'=>int}]
    // list computed by the controller.
    $chartW = 660; $chartH = 250; $L = 42; $R = 12; $T = 14; $B = 34;
    $hasData = collect($series)->sum('count') > 0;
    // The reference's axis is a fixed 0..100 scale because its demo data happens to fit
    // it. Real counts vary a lot (a quiet org might never clear single digits), so the
    // ceiling is derived from the real max instead of hardcoded — same 6-gridline shape,
    // honest numbers.
    $rawMax = max(1, collect($series)->max('count'));
    $axisMax = max(20, (int) (ceil($rawMax / 20) * 20));
    $n = count($series);
    $stepX = ($chartW - $L - $R) / max(1, $n - 1);
    $x = fn (int $i) => $L + $i * $stepX;
    $y = fn (int|float $v) => $T + (1 - $v / $axisMax) * ($chartH - $T - $B);
    $points = collect($series)->values()->map(fn ($d, $i) => ['x' => $x($i), 'y' => $y($d['count'])])->all();

    $linePath = '';
    $areaPath = '';
    if ($hasData) {
        $linePath = sprintf('M%.2f,%.2f', $points[0]['x'], $points[0]['y']);
        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $points[max($i - 1, 0)]; $p1 = $points[$i]; $p2 = $points[$i + 1]; $p3 = $points[min($i + 2, $n - 1)];
            $linePath .= sprintf(
                ' C%.2f,%.2f %.2f,%.2f %.2f,%.2f',
                $p1['x'] + ($p2['x'] - $p0['x']) / 6, $p1['y'] + ($p2['y'] - $p0['y']) / 6,
                $p2['x'] - ($p3['x'] - $p1['x']) / 6, $p2['y'] - ($p3['y'] - $p1['y']) / 6,
                $p2['x'], $p2['y'],
            );
        }
        $areaPath = $linePath." L{$points[$n - 1]['x']},".($chartH - $B)." L{$points[0]['x']},".($chartH - $B).' Z';
    }
    $chartId = 'chart-'.\Illuminate\Support\Str::random(8);
@endphp
<div class="activity-chart-wrap" id="{{ $chartId }}">
    <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" class="activity-chart">
        @for($g = 0; $g <= 5; $g++)
            @php($gy = $y($axisMax / 5 * $g))
            <line x1="{{ $L }}" y1="{{ $gy }}" x2="{{ $chartW - $R }}" y2="{{ $gy }}" class="activity-grid"/>
            <text x="{{ $L - 10 }}" y="{{ $gy + 4 }}" text-anchor="end" class="activity-axis-label">{{ (int) ($axisMax / 5 * $g) }}</text>
        @endfor

        @if($hasData)
            <defs>
                <linearGradient id="{{ $chartId }}-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#F26B21" stop-opacity=".28"/>
                    <stop offset="100%" stop-color="#F26B21" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <path d="{{ $areaPath }}" fill="url(#{{ $chartId }}-fill)"/>
            <path d="{{ $linePath }}" class="activity-line"/>
            <circle cx="{{ $points[0]['x'] }}" cy="{{ $points[0]['y'] }}" r="4.5" class="activity-dot"/>
            <circle cx="{{ $points[$n - 1]['x'] }}" cy="{{ $points[$n - 1]['y'] }}" r="4.5" class="activity-dot"/>
            <circle class="activity-marker" r="7" cx="0" cy="0" opacity="0"/>
            @foreach($points as $i => $p)
                <circle class="activity-hit" data-i="{{ $i }}" cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="14" fill="transparent"/>
            @endforeach
        @else
            <text x="{{ $L + ($chartW - $L - $R) / 2 }}" y="{{ $T + ($chartH - $T - $B) / 2 }}" text-anchor="middle" class="activity-empty-label">
                {{ __('agencyos.dashboard_admin.activity_empty') }}
            </text>
        @endif

        @foreach($series as $i => $d)
            @if($i % 2 === 0 || $i === $n - 1)
                <text x="{{ $x($i) }}" y="{{ $chartH - 10 }}" text-anchor="middle" class="activity-axis-label">{{ $d['date']->translatedFormat('M j') }}</text>
            @endif
        @endforeach
    </svg>
    @if($hasData)
        <div class="activity-tooltip" hidden>
            <div class="activity-tooltip-date"></div>
            <div class="activity-tooltip-val"></div>
        </div>
    @endif
</div>
@if($hasData)
    <script>
    (function () {
        var wrap = document.getElementById({{ \Illuminate\Support\Js::from($chartId) }});
        if (!wrap) return;
        var svg = wrap.querySelector('svg');
        var marker = wrap.querySelector('.activity-marker');
        var tooltip = wrap.querySelector('.activity-tooltip');
        var dateEl = tooltip.querySelector('.activity-tooltip-date');
        var valEl = tooltip.querySelector('.activity-tooltip-val');
        var chartW = {{ $chartW }}, chartH = {{ $chartH }};
        var series = {{ \Illuminate\Support\Js::from(collect($series)->map(fn ($d) => ['date' => $d['date']->translatedFormat('D, M j'), 'count' => $d['count']])) }};
        var points = {{ \Illuminate\Support\Js::from($points) }};

        wrap.querySelectorAll('.activity-hit').forEach(function (hit) {
            hit.addEventListener('mouseenter', function () {
                var i = parseInt(hit.dataset.i, 10);
                var p = points[i];
                marker.setAttribute('cx', p.x);
                marker.setAttribute('cy', p.y);
                marker.setAttribute('opacity', 1);

                var rect = svg.getBoundingClientRect();
                var left = (p.x / chartW) * rect.width;
                var top = (p.y / chartH) * rect.height;
                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
                dateEl.textContent = series[i].date;
                valEl.textContent = series[i].count + ' {{ __('agencyos.dashboard_admin.activity_tooltip_unit') }}';
                tooltip.hidden = false;
            });
        });
        wrap.addEventListener('mouseleave', function () {
            marker.setAttribute('opacity', 0);
            tooltip.hidden = true;
        });
    })();
    </script>
@endif
