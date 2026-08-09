@props(['name', 'options', 'selected' => null, 'placeholder' => null, 'required' => false, 'onchange' => null])
@php
    $uid = 'sel-'.\Illuminate\Support\Str::random(8);
    $opts = collect($options);
    $hasSelection = $selected !== null && $selected !== '' && $opts->has((string) $selected);
    // Mirrors a plain <select>'s own default: with no placeholder and nothing marked
    // selected, the browser just shows the first <option> — so the custom button and
    // .qa-menu highlight need to agree with that instead of rendering blank.
    $effectiveValue = $hasSelection ? (string) $selected : ($placeholder !== null ? '' : (string) $opts->keys()->first());
    $currentLabel = match (true) {
        $hasSelection => $opts[(string) $selected],
        $placeholder !== null => $placeholder,
        default => $opts->first(),
    };
@endphp
{{--
    The real <select> stays in the DOM (visually hidden, not display:none) as the single
    source of truth: it's what the form submits, what `required` validates, what old()
    re-selects on a validation bounce-back, and what any external onchange handler (see
    users/create.blade.php's role -> department toggle) still reads/writes via
    `field.querySelector('select[name=...]')`. The button + .qa-menu below are a pure
    visual layer that reads/writes that select's value — never a second source of truth.
--}}
<div class="qa-wrap form-select" id="{{ $uid }}">
    <select name="{{ $name }}" @required($required) @if($onchange) onchange="{{ $onchange }}" @endif>
        @if($placeholder)
            <option value="" @selected(! $hasSelection)>{{ $placeholder }}</option>
        @endif
        @foreach($opts as $value => $optLabel)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $optLabel }}</option>
        @endforeach
    </select>
    <button type="button" class="form-select-btn" id="{{ $uid }}-btn" aria-haspopup="true" aria-expanded="false" aria-controls="{{ $uid }}-menu">
        <span>{{ $currentLabel }}</span>
        <x-icon name="chevron-down" class="ic"/>
    </button>
    <div class="qa-menu hide" id="{{ $uid }}-menu" role="menu">
        @if($placeholder)
            <button type="button" class="drop-item {{ $effectiveValue === '' ? 'is-selected' : '' }}" data-value="">{{ $placeholder }}</button>
        @endif
        @foreach($opts as $value => $optLabel)
            <button type="button" class="drop-item {{ $effectiveValue === (string) $value ? 'is-selected' : '' }}" data-value="{{ $value }}">{{ $optLabel }}</button>
        @endforeach
    </div>
</div>
<script>
(function () {
    var wrap = document.getElementById({{ \Illuminate\Support\Js::from($uid) }});
    var select = wrap.querySelector('select');
    var btn = document.getElementById({{ \Illuminate\Support\Js::from($uid.'-btn') }});
    var label = btn.querySelector('span');
    var menu = document.getElementById({{ \Illuminate\Support\Js::from($uid.'-menu') }});
    var placeholder = {{ \Illuminate\Support\Js::from($placeholder) }};

    function close() {
        menu.classList.add('hide'); btn.setAttribute('aria-expanded', 'false');
        if (window.__agencyosCloseOpenMenu === close) window.__agencyosCloseOpenMenu = null;
    }
    function open() {
        if (window.__agencyosCloseOpenMenu) window.__agencyosCloseOpenMenu();
        menu.classList.remove('hide'); btn.setAttribute('aria-expanded', 'true');
        window.__agencyosCloseOpenMenu = close;
    }
    function sync() {
        var opt = select.options[select.selectedIndex];
        label.textContent = opt ? opt.textContent : (placeholder || '');
        menu.querySelectorAll('.drop-item').forEach(function (item) {
            item.classList.toggle('is-selected', item.dataset.value === select.value);
        });
    }

    menu.querySelectorAll('.drop-item').forEach(function (item) {
        item.addEventListener('click', function () {
            select.value = item.dataset.value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            close();
        });
    });
    // Keeps the button label in sync when the select's value changes from outside this
    // widget too — e.g. a keyboard user tabbing into the (visually hidden) native select
    // and using arrow keys, or an external script like agencyosToggleUserDepartmentField
    // that resets select.value directly and dispatches its own change event.
    select.addEventListener('change', sync);

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
