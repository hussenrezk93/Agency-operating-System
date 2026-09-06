@props(['mainNav', 'workspaceNav', 'navLink'])
@php
    // Mobile-only replacement for the burger/drawer trigger — 4 fixed tabs (Dashboard,
    // the role's primary work item, Chat, and More) share the row with a floating
    // Quick Action button notched into the middle. "More" reopens the SAME sidebar
    // drawer the old burger used (nav-toggle class, see layouts/app.blade.php's script),
    // so every other nav item still lives in one place instead of being duplicated here.
    [$primaryHref, $primaryIcon, $primaryLabel, $primaryActive] = $navLink($mainNav[0]);
    [$chatHref, $chatIcon, $chatLabel, $chatActive] = $navLink($workspaceNav[0]);
@endphp
<nav class="bottom-nav">
    <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>
        <x-icon name="home" class="ic"/>
        <span>{{ __('agencyos.dashboard.page_title') }}</span>
    </a>
    <a href="{{ $primaryHref }}" @class(['active' => $primaryActive])>
        <x-icon :name="$primaryIcon" class="ic"/>
        <span>{{ $primaryLabel }}</span>
    </a>
    {{-- Always a 3rd grid slot even when the role has no quick actions (Employee) —
         <x-quick-action> then renders nothing inside it, leaving an empty but
         correctly-sized cell instead of shifting Chat/More left by one column. --}}
    <span class="bottom-nav-fab-slot"><x-quick-action variant="fab"/></span>
    <a href="{{ $chatHref }}" @class(['active' => $chatActive])>
        <x-icon :name="$chatIcon" class="ic"/>
        <span>{{ $chatLabel }}</span>
    </a>
    <button type="button" class="nav-toggle">
        <x-icon name="menu" class="ic"/>
        <span>{{ app()->isLocale('ar') ? 'المزيد' : 'More' }}</span>
    </button>
</nav>
