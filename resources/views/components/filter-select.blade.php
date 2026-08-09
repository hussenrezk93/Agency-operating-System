@props(['name', 'options', 'selected' => null, 'placeholder', 'allowClear' => true])
@php
    $id = 'fs-'.\Illuminate\Support\Str::random(8);
    $hasValue = $selected !== null && $selected !== '';
    $currentLabel = $hasValue && isset($options[$selected]) ? $options[$selected] : $placeholder;
@endphp
<div class="qa-wrap filter-select">
    <button type="button" class="btn-filter-select {{ $hasValue && $allowClear ? 'is-active' : '' }}" id="{{ $id }}-btn"
            aria-haspopup="true" aria-expanded="false" aria-controls="{{ $id }}-menu">
        <span>{{ $currentLabel }}</span>
        <x-icon name="chevron-down" class="ic"/>
    </button>
    <div class="qa-menu hide" id="{{ $id }}-menu" role="menu">
        @if($allowClear)
            <a class="drop-item {{ ! $hasValue ? 'is-selected' : '' }}" href="{{ request()->fullUrlWithQuery([$name => null]) }}" role="menuitem">{{ $placeholder }}</a>
        @endif
        @foreach($options as $value => $label)
            <a class="drop-item {{ (string) $selected === (string) $value ? 'is-selected' : '' }}" href="{{ request()->fullUrlWithQuery([$name => $value]) }}" role="menuitem">{{ $label }}</a>
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
