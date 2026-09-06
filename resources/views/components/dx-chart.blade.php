@props(['series', 'unit', 'title' => null])

@php
    // Geometry matches the approved dashboard mockup's SVG bar chart exactly: a
    // 420x250 viewBox, plot area x:32->414 y:16->224, one label every 3rd bar, a
    // "nice" 5-step y-axis, and a rounded-top bar centered in a full-width invisible
    // hit column (so hovering anywhere in a day's column shows its tooltip, not just
    // the bar itself).
    $W = 420; $H = 250;
    $padL = 32; $axisY = 224; $topY = 16; $labelY = 241;
    $plotW = $W - 6 - $padL;
    $plotH = $axisY - $topY;
    $n = max(1, count($series));
    $colW = $plotW / $n;

    $actualMax = (int) collect($series)->max('count');
    $tickStep = max(1, (int) ceil(max(1, $actualMax) / 5));
    $niceMax = $tickStep * 5;

    $id = 'dxbar-'.\Illuminate\Support\Str::random(8);
    $tooltipData = [];
@endphp
<div class="dx-chart-wrap" id="{{ $id }}">
    <div class="dx-chart">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="{{ $title }}">
            <defs>
                <pattern id="{{ $id }}-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                    <rect width="6" height="6" fill="var(--dx-accent)"/>
                    <line x1="0" y1="0" x2="0" y2="6" stroke="#fff" stroke-width="2.4" opacity=".38"/>
                </pattern>
            </defs>

            @for($k = 0; $k <= 5; $k++)
                @php
                    $value = $k * $tickStep;
                    $y = $axisY - ($value / $niceMax) * $plotH;
                @endphp
                <line class="dx-bar-grid" x1="{{ $padL }}" y1="{{ round($y, 2) }}" x2="{{ $W - 6 }}" y2="{{ round($y, 2) }}"/>
                <text class="dx-bar-axis" x="{{ $padL - 9 }}" y="{{ round($y + 3.5, 2) }}" text-anchor="end">{{ $value }}</text>
            @endfor

            @foreach($series as $i => $day)
                @php
                    $colX = $padL + $i * $colW;
                    $cx = round($colX + $colW / 2, 2);
                    $count = (int) $day['count'];
                    $barTopY = $count > 0 ? $axisY - ($count / $niceMax) * $plotH : $axisY;
                    $tooltipData[] = [
                        'cx' => $cx,
                        'y' => round($barTopY, 2),
                        'count' => $count,
                        'label' => $day['date']->translatedFormat('D, M j'),
                    ];
                @endphp
                <g class="dx-bar" data-i="{{ $i }}">
                    @if($count > 0)
                        @php
                            $barH = $axisY - $barTopY;
                            $barW = $colW * 0.52;
                            $barX = $colX + ($colW - $barW) / 2;
                            $r = min(6, $barW / 2, $barH / 2);
                            $fill = ($actualMax > 0 && $count === $actualMax) ? 'var(--dx-ink)' : 'url(#'.$id.'-hatch)';
                        @endphp
                        <path d="M{{ round($barX, 2) }},{{ $axisY }} L{{ round($barX, 2) }},{{ round($barTopY + $r, 2) }} Q{{ round($barX, 2) }},{{ round($barTopY, 2) }} {{ round($barX + $r, 2) }},{{ round($barTopY, 2) }} L{{ round($barX + $barW - $r, 2) }},{{ round($barTopY, 2) }} Q{{ round($barX + $barW, 2) }},{{ round($barTopY, 2) }} {{ round($barX + $barW, 2) }},{{ round($barTopY + $r, 2) }} L{{ round($barX + $barW, 2) }},{{ $axisY }} Z" fill="{{ $fill }}"/>
                    @endif
                    <rect class="dx-bar-hit" data-i="{{ $i }}" x="{{ round($colX, 2) }}" y="{{ $topY }}" width="{{ round($colW, 2) }}" height="{{ $plotH }}" fill="transparent"/>
                </g>
            @endforeach

            @foreach($series as $i => $day)
                @if($i % 3 === 0)
                    <text class="dx-bar-axis" x="{{ round($padL + $i * $colW + $colW / 2, 2) }}" y="{{ $labelY }}" text-anchor="middle">{{ $day['date']->translatedFormat('M j') }}</text>
                @endif
            @endforeach
        </svg>
    </div>
    <div class="dx-tip" hidden>
        <b></b>
        <span></span>
    </div>
</div>
<script>
(function () {
    var wrap = document.getElementById({{ \Illuminate\Support\Js::from($id) }});
    if (!wrap) return;

    var svg = wrap.querySelector('svg');
    var tip = wrap.querySelector('.dx-tip');
    var tipVal = tip.querySelector('b');
    var tipDate = tip.querySelector('span');
    var data = {{ \Illuminate\Support\Js::from($tooltipData) }};
    var unit = {{ \Illuminate\Support\Js::from($unit) }};
    var W = {{ $W }}, H = {{ $H }};

    wrap.querySelectorAll('.dx-bar-hit').forEach(function (hit) {
        hit.addEventListener('mouseenter', function () {
            var d = data[parseInt(hit.dataset.i, 10)];
            if (!d) return;

            var rect = svg.getBoundingClientRect();
            tip.style.left = ((d.cx / W) * rect.width) + 'px';
            tip.style.top = ((d.y / H) * rect.height) + 'px';
            tipVal.textContent = d.count + ' ' + unit;
            tipDate.textContent = d.label;
            tip.hidden = false;
        });
    });

    wrap.addEventListener('mouseleave', function () { tip.hidden = true; });
})();
</script>
