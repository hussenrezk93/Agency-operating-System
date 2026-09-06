@php
    $currentUser = auth()->user();
    $role = $currentUser?->roleCode()->value ?? 'employee';
    $roleLabel = __('agencyos.roles.'.$role);
    $uiUrl = fn (string $screen) => route('approved-ui', ['screen' => $screen]);
    $unreadNotificationCount = $currentUser?->unreadNotifications()->count() ?? 0;
    // Whether today's Daily Department Report run has happened yet — only checked for
    // the two roles that ever see the nav link below, since it's a real query per page
    // load (same cost class as the unread-notification count just above).
    $dailyReportReady = in_array($role, ['tl', 'manager'], true)
        && \App\Models\DepartmentDailyReport::whereDate('report_date', now())->exists();
    // The Moderator department's TL gets a second, distinct nav item for Content's
    // handoff — "ready" here means Content has actually submitted it (something new to
    // read), a different signal from $dailyReportReady above (generation having run).
    $isModeratorTl = $role === 'tl'
        && $currentUser?->department?->special_role === \App\Enums\DepartmentSpecialRole::Moderator;
    $inboxReady = $isModeratorTl && \App\Models\DepartmentDailyReport::query()
        ->where('type', 'moderator_handoff')
        ->whereDate('report_date', now())
        ->whereNotNull('submitted_at')
        ->exists();
    // Which approved screen is on screen right now, so the sidebar can mark it active
    // instead of permanently claiming the dashboard. Null when a Blade page is showing.
    $currentScreen = request()->routeIs('approved-ui')
        ? (trim((string) request()->route('screen'), '/') ?: 'index.html')
        : null;
    // MAIN = configuration/management screens for this role; WORKSPACE = everyday,
    // non-administrative screens (chat, notifications, profile). Dashboard is always
    // the first MAIN item, rendered separately below rather than in these arrays.
    [$mainNav, $workspaceNav] = match($role) {
        'admin' => [[
            ['real:users.index', 'users', app()->isLocale('ar') ? 'المستخدمون' : 'Users'],
            ['real:departments.index', 'building-2', app()->isLocale('ar') ? 'الأقسام' : 'Departments'],
            ['real:department-routes.index', 'shuffle', app()->isLocale('ar') ? 'صلاحيات التحويل' : 'Routing permissions'],
            ['real:department-output-access.index', 'layout-grid', app()->isLocale('ar') ? 'صلاحيات المخرجات' : 'Output access'],
            ['real:audit-log.index', 'shield-check', app()->isLocale('ar') ? 'سجل التدقيق' : 'Audit log'],
        ], [
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ]],
        'manager' => [[
            ['real:tasks.index', 'list-checks', app()->isLocale('ar') ? 'المهام' : 'Tasks'],
            ['real:projects.index', 'folder-kanban', app()->isLocale('ar') ? 'المشروعات' : 'Projects'],
            ['real:clients.index', 'star', app()->isLocale('ar') ? 'العملاء' : 'Clients'],
            ['real:reports.index', 'bar-chart-3', app()->isLocale('ar') ? 'التقارير' : 'Reports'],
            ['real:payroll.index', 'wallet', app()->isLocale('ar') ? 'الرواتب' : 'Salaries'],
            ['real:payroll.expenses.index', 'receipt', app()->isLocale('ar') ? 'المصاريف' : 'Spending'],
            ['real:users.index', 'users', app()->isLocale('ar') ? 'المستخدمون' : 'Users'],
            ['real:departments.index', 'building-2', app()->isLocale('ar') ? 'الأقسام' : 'Departments'],
            ['real:temporary-leadership.index', 'clock', app()->isLocale('ar') ? 'قائد الفريق المؤقت' : 'Temporary TL'],
        ], [
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ]],
        'tl' => [[
            ['real:tasks.index', 'list-checks', app()->isLocale('ar') ? 'المهام' : 'Tasks'],
            ['real:tasks.index:review', 'search', app()->isLocale('ar') ? 'قائمة المراجعة' : 'Review queue'],
            ['real:tasks.index:my', 'check-circle', app()->isLocale('ar') ? 'مهامي' : 'My tasks'],
            ['real:projects.index', 'folder-kanban', app()->isLocale('ar') ? 'المشروعات' : 'Projects'],
            ['real:clients.index', 'star', app()->isLocale('ar') ? 'العملاء' : 'Clients'],
            ['real:reports.index', 'bar-chart-3', app()->isLocale('ar') ? 'التقارير' : 'Reports'],
        ], [
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ]],
        default => [[
            ['real:tasks.index', 'list-checks', app()->isLocale('ar') ? 'مهامي' : 'My tasks'],
            // Only a department that writes its daily report collectively (Sales,
            // product decision 2026-09) gives an Employee anything to open here.
            ...(auth()->user()?->department?->special_role === \App\Enums\DepartmentSpecialRole::Sales
                ? [['real:department-reports.contribute', 'file-text', app()->isLocale('ar') ? 'تقريري اليومي' : 'My daily report']]
                : []),
            ['real:projects.index', 'folder-kanban', app()->isLocale('ar') ? 'المشروعات' : 'Projects'],
            ['real:performance.show', 'bar-chart-3', app()->isLocale('ar') ? 'الأداء' : 'Performance'],
        ], [
            ['real:chat.index', 'message-circle', app()->isLocale('ar') ? 'الدردشة' : 'Chat'],
        ]],
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#FC6E20">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
    {{-- filemtime cache-busting lets the .htaccess far-future cache header below be safe:
         a deploy that changes either file changes its URL, so nothing is ever served stale. --}}
    <link rel="stylesheet" href="{{ asset('assets/agencyos.css') }}?v={{ filemtime(public_path('assets/agencyos.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/agencyos-laravel.css') }}?v={{ filemtime(public_path('assets/agencyos-laravel.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/agencyos-dashboard.css') }}?v={{ filemtime(public_path('assets/agencyos-dashboard.css')) }}">
    {{-- Tailwind, page by page — pages not yet migrated simply never use its
         classes; both stylesheets loading together is the expected state
         during the migration, not a mistake. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
        /* :not(.page-header-row) matters, not just style — animating opacity forces a
           new stacking context on that element for as long as animation-name is set
           (fill-mode: both keeps it applied after the animation ends), which trapped
           the Quick Action/range dropdowns' z-index inside .page-header-row instead of
           letting them paint above the sibling <main> content below them. */
        .main > *:not(.topbar):not(.page-header-row) { animation: agencyos-fade 150ms ease-out both; }
        @keyframes agencyos-fade { from { opacity: 0; } to { opacity: 1; } }

        .nav a { transition: background-color .12s ease, color .12s ease; }

        @media (prefers-reduced-motion: reduce) {
            @view-transition { navigation: none; }
            .main > *:not(.topbar):not(.page-header-row) { animation: none; }
            .nav a { transition: none; }
        }
    </style>
    <title>{{ __('agencyos.common.system_name') }} — @yield('title', __('agencyos.dashboard.page_title'))</title>
</head>
<body data-role="{{ $role }}" data-page="@yield('page', 'dashboard')">
<script>
/* Runs before the sidebar paints, so a returning visitor who collapsed it never sees a
   full-width flash before it snaps narrow. */
try {
    if (localStorage.getItem('agencyos-sidebar-collapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
} catch (e) {}
</script>
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="sidebar-logo"><img src="{{ asset('images/logo.svg') }}" alt="Agency OS"></div>
            <div class="brand-name">Agency<b>OS</b></div>
        </div>
        <div class="sidebar-collapse-row">
            <button type="button" class="sidebar-collapse-btn" id="sidebarCollapseBtn" aria-label="{{ app()->isLocale('ar') ? 'طي/توسيع القائمة' : 'Collapse/expand sidebar' }}">
                <x-icon name="chevron-right"/>
            </button>
        </div>
        <div class="sidebar-user">
            <div class="avatar"><x-avatar :user="$currentUser"/></div>
            <div class="su-info">
                <div class="su-name">{{ $currentUser?->full_name ?? $currentUser?->username }}</div>
                <span class="role-chip role-{{ $role }}">{{ $roleLabel }}</span>
            </div>
        </div>
        @php
            // Rendered inline for each nav item's href/active-state so both groups below
            // share one code path — a trailing :my / :review picks a pre-set query
            // string on top of the plain route ("My tasks"/"Review queue" are both just
            // the real Tasks list with a filter baked in, not separate pages).
            // Every route prefix any nav item claims. A deeper one wins: "Spending" is
            // payroll.expenses.*, which the shorter payroll.* of "Salaries" would
            // otherwise light up as well, leaving both items looking current at once.
            $navPrefixes = collect(array_merge($mainNav, $workspaceNav))
                ->map(fn (array $item) => $item[0])
                ->filter(fn (string $screen) => str_starts_with($screen, 'real:'))
                ->map(fn (string $screen) => explode(':', substr($screen, 5))[0])
                ->map(function (string $key) {
                    $segments = explode('.', $key);

                    return count($segments) > 2 ? implode('.', array_slice($segments, 0, -1)) : $segments[0];
                })
                ->unique()->values()->all();

            $navLink = function (array $item) use ($uiUrl, $currentScreen, $navPrefixes) {
                [$screen, $icon, $label] = $item;
                if (str_starts_with($screen, 'real:')) {
                    $routeKey = substr($screen, 5);
                    $query = [];
                    if (str_ends_with($routeKey, ':my')) {
                        $routeKey = substr($routeKey, 0, -3);
                        $query = ['view' => 'my'];
                    } elseif (str_ends_with($routeKey, ':review')) {
                        $routeKey = substr($routeKey, 0, -7);
                        // The viewer's own review turn, not one stored status — the
                        // Content leader's queue sits at pending_content_review, in
                        // another department, so under_review would show them nothing.
                        $query = ['status' => \App\Http\Controllers\TaskController::AWAITING_MY_REVIEW];
                    }
                    $href = route($routeKey, $query);
                    $segments = explode('.', $routeKey);
                    $routePrefix = count($segments) > 2
                        ? implode('.', array_slice($segments, 0, -1))
                        : $segments[0];
                    $deeperOwnsIt = collect($navPrefixes)
                        ->filter(fn (string $p) => $p !== $routePrefix && str_starts_with($p, $routePrefix.'.'))
                        ->contains(fn (string $p) => request()->routeIs($p.'.*') || request()->routeIs($p));
                    $active = request()->routeIs($routePrefix.'.*')
                        && ! $deeperOwnsIt
                        && request()->query('view') === ($query['view'] ?? null)
                        && request()->query('status') === ($query['status'] ?? null);
                } else {
                    [$screenName, $query] = array_pad(explode('?', $screen, 2), 2, null);
                    $href = $uiUrl($screenName).($query ? '?'.$query : '');
                    $active = $currentScreen === $screenName;
                }

                return [$href, $icon, $label, $active];
            };
        @endphp
        <nav class="nav">
            <div class="nav-label">{{ app()->isLocale('ar') ? 'الرئيسية' : 'Main' }}</div>
            <a @class(['active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}"><span class="ic"><x-icon name="home"/></span><span>{{ __('agencyos.dashboard.page_title') }}</span></a>
            @foreach($mainNav as $item)
                @php([$href, $icon, $label, $active] = $navLink($item))
                <a @class(['active' => $active]) href="{{ $href }}"><span class="ic"><x-icon :name="$icon"/></span><span>{{ $label }}</span></a>
            @endforeach
            @if(in_array($role, ['tl', 'manager'], true))
                <a @class(['active' => request()->routeIs('department-reports.*'), 'nav-ready' => $dailyReportReady, 'nav-pending' => ! $dailyReportReady])
                   href="{{ route('department-reports.show', now()->toDateString()) }}">
                    <span class="ic"><x-icon name="calendar"/></span>
                    <span>{{ app()->isLocale('ar') ? 'التقرير اليومي' : 'Daily report' }}</span>
                </a>
            @endif
            @if($isModeratorTl)
                <a @class(['active' => request()->routeIs('department-reports.*'), 'nav-ready' => $inboxReady, 'nav-pending' => ! $inboxReady])
                   href="{{ route('department-reports.show', now()->toDateString()) }}">
                    <span class="ic"><x-icon name="mail"/></span>
                    <span>{{ app()->isLocale('ar') ? 'صندوق الوارد' : 'Inbox' }}</span>
                </a>
            @endif
            <div class="nav-label">{{ app()->isLocale('ar') ? 'مساحة العمل' : 'Workspace' }}</div>
            @foreach($workspaceNav as $item)
                @php([$href, $icon, $label, $active] = $navLink($item))
                <a @class(['active' => $active]) href="{{ $href }}"><span class="ic"><x-icon :name="$icon"/></span><span>{{ $label }}</span></a>
            @endforeach
            <a @class(['active' => request()->routeIs('profile.*')]) href="{{ route('profile.edit') }}"><span class="ic"><x-icon name="user"/></span><span>{{ app()->isLocale('ar') ? 'الملف الشخصي' : 'Profile' }}</span></a>
        </nav>
        <div class="sidebar-foot">
            <form class="logout-form" method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sidebar-logout" type="submit"><x-icon name="log-out"/><span>{{ __('agencyos.common.logout') }}</span></button>
            </form>
            <div class="sidebar-version">v1.1 · Africa/Cairo · Laravel</div>
        </div>
    </aside>
    <div class="main">
        @hasSection('page_header')
            {{-- A page that supplies its own greeting (currently: the dashboard) renders
                 title + controls on ONE transparent row instead of the chrome bar below
                 — no separate bar, no second block further down the page. --}}
            <div class="page-header-row">
                <button class="burger" id="burger" type="button" aria-label="Menu"><x-icon name="menu"/></button>
                @yield('page_header')
            </div>
        @else
            <header class="topbar">
                <button class="burger" id="burger" type="button" aria-label="Menu"><x-icon name="menu"/></button>
                <x-topbar-controls/>
            </header>
        @endif
        @if($currentUser && $currentUser->email_verified_at === null)
            <div class="alert alert-danger" style="margin:16px 16px 0">
                <div>
                    {{ __('agencyos.email_verification.banner_text') }}
                    <form method="POST" action="{{ route('email.resend-verification') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline" style="margin-inline-start:8px">{{ __('agencyos.email_verification.banner_resend') }}</button>
                    </form>
                </div>
            </div>
        @elseif($currentUser && $currentUser->pending_email !== null)
            <div class="alert alert-danger" style="margin:16px 16px 0">
                <div>
                    {{ __('agencyos.email_verification.pending_banner_text', ['email' => $currentUser->pending_email]) }}
                    <form method="POST" action="{{ route('email.resend-verification') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-outline" style="margin-inline-start:8px">{{ __('agencyos.email_verification.banner_resend') }}</button>
                    </form>
                </div>
            </div>
        @endif
        @yield('content')
    </div>
</div>
<x-bottom-nav :main-nav="$mainNav" :workspace-nav="$workspaceNav" :nav-link="$navLink"/>
<div class="avatar-lightbox" id="avatarLightbox" role="dialog" aria-modal="true" aria-hidden="true">
    <button type="button" class="avatar-lightbox-close" id="avatarLightboxClose" aria-label="{{ app()->isLocale('ar') ? 'إغلاق' : 'Close' }}"><x-icon name="x" class="ic"/></button>
    <img id="avatarLightboxImg" src="" alt="">
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
function toggleNavDrawer(){document.body.classList.toggle('nav-open');if(document.body.classList.contains('nav-open')){var s=document.createElement('div');s.className='scrim';s.addEventListener('click',function(){document.body.classList.remove('nav-open');s.remove();});document.body.appendChild(s);}}
var burger=document.getElementById('burger');
if(burger){burger.addEventListener('click',toggleNavDrawer);}
// The bottom-nav's "More" tab (mobile only) reopens the SAME drawer instead of
// duplicating every nav item down there — one source of truth for "everything else".
document.querySelectorAll('.nav-toggle').forEach(function(btn){btn.addEventListener('click',toggleNavDrawer);});

var collapseBtn=document.getElementById('sidebarCollapseBtn');
if(collapseBtn){collapseBtn.addEventListener('click',function(){var collapsed=document.body.classList.toggle('sidebar-collapsed');try{localStorage.setItem('agencyos-sidebar-collapsed',collapsed?'1':'0');}catch(e){}});}

// Delegated (not bound per-avatar) so it also catches avatars appended later by the
// live chat JS, not just the ones present at page load.
(function(){
    var lightbox=document.getElementById('avatarLightbox');
    var lightboxImg=document.getElementById('avatarLightboxImg');
    var closeBtn=document.getElementById('avatarLightboxClose');
    if(!lightbox||!lightboxImg||!closeBtn)return;

    function open(src,alt){
        lightboxImg.src=src;
        lightboxImg.alt=alt||'';
        lightbox.classList.add('open');
        lightbox.setAttribute('aria-hidden','false');
    }
    function close(){
        lightbox.classList.remove('open');
        lightbox.setAttribute('aria-hidden','true');
        lightboxImg.src='';
    }

    document.addEventListener('click',function(e){
        var img=e.target.closest?e.target.closest('img[data-lightbox]'):null;
        if(img){e.preventDefault();open(img.src,img.alt);return;}
        if(e.target===lightbox)close();
    });
    closeBtn.addEventListener('click',close);
    document.addEventListener('keydown',function(e){
        if(e.key==='Escape'&&lightbox.classList.contains('open'))close();
    });
})();
</script>
<x-gemini-widget/>
</body>
</html>
