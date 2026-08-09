@props(['days'])
@php
    $options = [7, 14, 30];
    $ar = app()->isLocale('ar');
    $label = fn (int $d) => $ar ? "آخر {$d} يوم" : "Last {$d} days";
    $id = 'rm-'.\Illuminate\Support\Str::random(8);
@endphp
<div class="qa-wrap">
    <button type="button" class="btn-range" id="{{ $id }}-btn" aria-haspopup="true" aria-expanded="false" aria-controls="{{ $id }}-menu">
        <span>{{ $label($days) }}</span>
        <x-icon name="chevron-down" class="ic"/>
    </button>
    <div class="qa-menu hide" id="{{ $id }}-menu" role="menu">
        @foreach($options as $opt)
            <a class="drop-item @if($opt === $days) is-selected @endif" href="{{ request()->fullUrlWithQuery(['range' => $opt]) }}" role="menuitem">
                <span>{{ $label($opt) }}</span>
                @if($opt === $days)<x-icon name="check-circle"/>@endif
            </a>
        @endforeach
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById({{ \Illuminate\Support\Js::from($id.'-btn') }});
    var menu = document.getElementById({{ \Illuminate\Support\Js::from($id.'-menu') }});
    if (!btn || !menu) return;

    function close() {
        menu.classList.add('hide'); btn.setAttribute('aria-expanded', 'false');
        if (window.__agencyosCloseOpenMenu === close) window.__agencyosCloseOpenMenu = null;
    }
    function open() {
        if (window.__agencyosCloseOpenMenu) window.__agencyosCloseOpenMenu();
        menu.classList.remove('hide'); btn.setAttribute('aria-expanded', 'true');
        window.__agencyosCloseOpenMenu = close;
    }

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.contains('hide') ? open() : close();
    });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && e.target !== btn) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
</script>
