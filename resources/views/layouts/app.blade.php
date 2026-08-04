@php
    $currentUser = auth()->user();
    $role = $currentUser?->roleCode()->value ?? 'employee';
    $roleLabel = __('agencyos.roles.'.$role);
    $initials = collect(preg_split('/\s+/u', trim($currentUser?->full_name ?? $currentUser?->username ?? 'U')))->filter()->take(2)->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->implode('');
    $uiUrl = fn (string $screen) => route('approved-ui', ['screen' => $screen]);
    $unreadNotificationCount = $currentUser?->unreadNotifications()->count() ?? 0;
    // Which approved screen is on screen right now, so the sidebar can mark it active
    // instead of permanently claiming the dashboard. Null when a Blade page is showing.
    $currentScreen = request()->routeIs('approved-ui')
        ? (trim((string) request()->route('screen'), '/') ?: 'index.html')
        : null;
    $workspaceNav = match($role) {
        'admin' => [
            ['real:users.index', 'users', app()->isLocale('ar') ? 'المديرون' : 'Managers'],
            ['real:department-routes.index', 'shuffle', app()->isLocale('ar') ? 'صلاحيات التحويل' : 'Routing permissions'],
            ['real:department-output-access.index', 'layout-grid', app()->isLocale('ar') ? 'صلاحيات المخرجات' : 'Output access'],
            ['system-settings.html', 'settings', app()->isLocale('ar') ? 'إعدادات النظام' : 'System settings'],
            ['audit-log.html', 'shield-check', app()->isLocale('ar') ? 'سجل التدقيق' : 'Audit log'],
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ],
        'manager' => [
            ['real:tasks.index', 'list-checks', app()->isLocale('ar') ? 'المهام' : 'Tasks'],
            ['real:projects.index', 'folder-kanban', app()->isLocale('ar') ? 'المشروعات' : 'Projects'],
            ['real:clients.index', 'star', app()->isLocale('ar') ? 'العملاء' : 'Clients'],
            ['real:reports.index', 'bar-chart-3', app()->isLocale('ar') ? 'التقارير' : 'Reports'],
            ['real:users.index', 'users', app()->isLocale('ar') ? 'المستخدمون' : 'Users'],
            ['real:departments.index', 'building-2', app()->isLocale('ar') ? 'الأقسام' : 'Departments'],
            ['real:temporary-leadership.index', 'clock', app()->isLocale('ar') ? 'قائد الفريق المؤقت' : 'Temporary TL'],
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ],
        'tl' => [
            ['real:tasks.index', 'list-checks', app()->isLocale('ar') ? 'المهام' : 'Tasks'],
            ['tl-review.html', 'search', app()->isLocale('ar') ? 'قائمة المراجعة' : 'Review queue'],
            ['real:tasks.index:my', 'check-circle', app()->isLocale('ar') ? 'مهامي' : 'My tasks'],
            ['real:projects.index', 'folder-kanban', app()->isLocale('ar') ? 'المشروعات' : 'Projects'],
            ['real:clients.index', 'star', app()->isLocale('ar') ? 'العملاء' : 'Clients'],
            ['real:reports.index', 'bar-chart-3', app()->isLocale('ar') ? 'التقارير' : 'Reports'],
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ],
        default => [
            ['real:tasks.index', 'list-checks', app()->isLocale('ar') ? 'مهامي' : 'My tasks'],
            ['real:projects.index', 'folder-kanban', app()->isLocale('ar') ? 'المشروعات' : 'Projects'],
            ['real:performance.show', 'bar-chart-3', app()->isLocale('ar') ? 'الأداء' : 'Performance'],
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#F97316">
    <link rel="icon" type="image/png" href="{{ asset('images/agencyos-favicon-64.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/agencyos.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/agencyos-laravel.css') }}">
    <style>
        /* SAFE SMOOTH NAVIGATION — CSS only.
           Native cross-document View Transitions where the browser supports them; every
           other browser performs an ordinary navigation and simply ignores the at-rule.
           No JavaScript touches links, forms, history or content, so Ctrl+click,
           middle-click, open-in-new-tab, Back and Forward are unaffected. */
        @view-transition { navigation: auto; }

        /* Naming the chrome keeps the sidebar and topbar visually anchored while only
           the page body cross-fades. */
        .sidebar { view-transition-name: agencyos-sidebar; }
        .topbar  { view-transition-name: agencyos-topbar; }

        ::view-transition-old(root),
        ::view-transition-new(root) { animation-duration: 160ms; }

        /* Fallback for browsers without View Transitions: a 150ms opacity settle on the
           content only. No overlay, no loading bar, no delay before navigating. */
        .main > *:not(.topbar) { animation: agencyos-fade 150ms ease-out both; }
        @keyframes agencyos-fade { from { opacity: 0; } to { opacity: 1; } }

        .nav a { transition: background-color .12s ease, color .12s ease; }

        @media (prefers-reduced-motion: reduce) {
            @view-transition { navigation: none; }
            .main > *:not(.topbar) { animation: none; }
            .nav a { transition: none; }
        }
    </style>
    <title>{{ __('agencyos.common.system_name') }} — @yield('title', __('agencyos.dashboard.page_title'))</title>
</head>
<body data-role="{{ $role }}" data-page="@yield('page', 'dashboard')">
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="sidebar-logo"><img src="{{ asset('images/agencyos-company-logo-white.png') }}" alt="Agency OS"></div>
            <div><div class="brand-name">Agency<b>OS</b></div><div class="small muted">Workflow Management</div></div>
        </div>
        <span class="role-chip role-{{ $role }}">{{ $roleLabel }}</span>
        <nav class="nav">
            <div class="nav-label">{{ app()->isLocale('ar') ? 'الرئيسية' : 'Main' }}</div>
            <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span class="ic"><x-icon name="home"/></span><span>{{ __('agencyos.dashboard.page_title') }}</span></a>
            <div class="nav-label">{{ app()->isLocale('ar') ? 'مساحة العمل' : 'Workspace' }}</div>
            @foreach($workspaceNav as [$screen, $icon, $label])
                @if(str_starts_with($screen, 'real:'))
                    @php
                        $routeKey = substr($screen, 5);
                        $isMyTasks = str_ends_with($routeKey, ':my');
                        $routeName = $isMyTasks ? substr($routeKey, 0, -3) : $routeKey;
                        $href = route($routeName, $isMyTasks ? ['view' => 'my'] : []);
                        $routePrefix = explode('.', $routeName)[0];
                        $isActive = request()->routeIs($routePrefix.'.*') && ($isMyTasks === (request()->query('view') === 'my'));
                    @endphp
                    <a @class(['active' => $isActive]) href="{{ $href }}"><span class="ic"><x-icon :name="$icon"/></span><span>{{ $label }}</span></a>
                @else
                    @php
                        [$screenName, $query] = array_pad(explode('?', $screen, 2), 2, null);
                        $href = $uiUrl($screenName).($query ? '?'.$query : '');
                    @endphp
                    <a @class(['active' => $currentScreen === $screenName]) href="{{ $href }}"><span class="ic"><x-icon :name="$icon"/></span><span>{{ $label }}</span></a>
                @endif
            @endforeach
            <a @class(['active' => request()->routeIs('notifications.*')]) href="{{ route('notifications.index') }}">
                <span class="ic"><x-icon name="bell"/></span><span>{{ app()->isLocale('ar') ? 'الإشعارات' : 'Notifications' }}</span>
                @if($unreadNotificationCount > 0)<span class="badge b-changes" style="margin-left:6px">{{ $unreadNotificationCount }}</span>@endif
            </a>
            <a @class(['active' => $currentScreen === 'profile.html']) href="{{ $uiUrl('profile.html') }}"><span class="ic"><x-icon name="user"/></span><span>{{ app()->isLocale('ar') ? 'الملف الشخصي' : 'Profile' }}</span></a>
        </nav>
        <div class="sidebar-foot">v1.1 · Africa/Cairo · Laravel</div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="burger" id="burger" type="button" aria-label="Menu"><x-icon name="menu"/></button>
            @if($role !== 'admin')
                <form class="searchbox" method="GET" action="{{ route('search.index') }}">
                    <span class="ic"><x-icon name="search"/></span>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ app()->isLocale('ar') ? 'البحث في النظام...' : 'Search Agency OS...' }}">
                </form>
            @else
                <div class="searchbox"></div>
            @endif
            <div class="topbar-actions">
                <form class="language-form" method="POST" action="{{ route('locale.update') }}">@csrf
                    <div class="language-switch" data-active="{{ app()->getLocale() }}">
                        <button type="submit" name="locale" value="en"><span class="lang-choice lang-en">EN</span></button>
                        <button type="submit" name="locale" value="ar"><span class="lang-choice lang-ar">العربية</span></button>
                    </div>
                </form>
                <a class="icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifications"><x-icon name="bell" class="ic"/>@if($unreadNotificationCount > 0)<sup>{{ $unreadNotificationCount }}</sup>@endif</a>
                <div class="userbox"><div class="avatar">{{ $initials ?: 'U' }}</div><div><div class="uname">{{ $currentUser?->full_name ?? $currentUser?->username }}</div><div class="urole">{{ $roleLabel }}</div></div></div>
                <form class="logout-form" method="POST" action="{{ route('logout') }}">@csrf<button class="lang-btn" type="submit">{{ __('agencyos.common.logout') }}</button></form>
            </div>
        </header>
        @if($currentUser && $currentUser->email_verified_at === null)
            <div class="alert alert-danger" style="margin:16px 16px 0">
                <div>
                    {{ __('agencyos.email_verification.banner_text') }}
                    <form method="POST" action="{{ route('email.resend-verification') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline" style="margin-left:8px">{{ __('agencyos.email_verification.banner_resend') }}</button>
                    </form>
                </div>
            </div>
        @endif
        @yield('content')
    </div>
</div>
<script>
/* Closes the mobile drawer when a link is taken. It does NOT preventDefault, so the
   browser navigates normally and Back/Forward stay correct. */
document.addEventListener('click',function(e){
    var link=e.target.closest?e.target.closest('.sidebar a'):null;
    if(!link)return;
    document.body.classList.remove('nav-open');
    var sc=document.querySelector('.scrim');if(sc)sc.remove();
});
var burger=document.getElementById('burger');
if(burger){burger.addEventListener('click',function(){document.body.classList.toggle('nav-open');if(document.body.classList.contains('nav-open')){var s=document.createElement('div');s.className='scrim';s.addEventListener('click',function(){document.body.classList.remove('nav-open');s.remove();});document.body.appendChild(s);}});}
</script>
</body>
</html>
