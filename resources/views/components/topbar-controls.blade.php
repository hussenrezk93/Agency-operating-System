@php
    $currentUser = auth()->user();
    $unreadNotificationCount = $currentUser?->unreadNotifications()->count() ?? 0;
    // A short preview only — the bell opens a quick-glance dropdown, not the full
    // paginated list (still reachable via its own "View all" link). Same component
    // rendered on every page, so this stays a tight limit rather than the index page's.
    $recentNotifications = $currentUser?->notifications()->latest('created_at')->limit(6)->get() ?? collect();
    // Admin's search is scoped to Users/Departments (BRD §15 keeps task/project content
    // out of reach), so it points at a different route/results page than every other
    // role's task/project search — same input, same "/" shortcut, different query target.
    $isAdmin = $currentUser?->roleCode()->value === 'admin';
@endphp
<div class="topbar-controls">
    <form class="searchbox" method="GET" action="{{ route('search.index') }}">
        <span class="ic"><x-icon name="search"/></span>
        <input type="search" name="q" id="topbarSearch" value="{{ request('q') }}" placeholder="{{ $isAdmin ? (app()->isLocale('ar') ? 'البحث عن مستخدم أو قسم...' : 'Search users, departments...') : (app()->isLocale('ar') ? 'البحث في النظام...' : 'Search Agency OS...') }}">
        <kbd>/</kbd>
    </form>
    <x-quick-action/>
    <div class="topbar-actions">
        <a class="locale-pill" href="{{ request()->fullUrlWithQuery(['set_locale' => app()->isLocale('ar') ? 'en' : 'ar']) }}">
            <x-icon name="globe" class="ic"/>
            <span>{{ strtoupper(str_replace('_', '-', app()->getLocale())) }}</span>
            <x-icon name="chevron-down" class="ic"/>
        </a>
        <div class="qa-wrap">
            <button type="button" class="icon-btn" id="notif-toggle" aria-haspopup="true" aria-expanded="false" aria-controls="notif-menu" aria-label="{{ app()->isLocale('ar') ? 'الإشعارات' : 'Notifications' }}">
                <x-icon name="bell" class="ic"/>
                <sup data-notif-badge id="notif-badge" style="{{ $unreadNotificationCount > 0 ? '' : 'display:none' }}">{{ $unreadNotificationCount }}</sup>
            </button>
            <div class="qa-menu notif-menu hide" id="notif-menu" role="menu">
                <div class="drop-head">
                    <span>{{ __('agencyos.notifications.index.title') }}</span>
                    <button type="button" id="notif-mark-all" data-url="{{ route('notifications.read-all') }}" style="{{ $unreadNotificationCount > 0 ? '' : 'display:none' }}">{{ __('agencyos.notifications.index.mark_all_read') }}</button>
                </div>
                <div class="notif-list" id="notif-list">
                    @forelse($recentNotifications as $notification)
                        <div class="drop-item @unless($notification->is_read) is-unread @endunless" data-id="{{ $notification->id }}" data-url="{{ route('notifications.read', $notification) }}" role="menuitem" tabindex="0">
                            <div>
                                <div class="notif-item-title">{{ $notification->title }}</div>
                                <div class="notif-item-time">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="drop-item" style="color:var(--color-text-muted)">{{ __('agencyos.notifications.index.empty') }}</div>
                    @endforelse
                </div>
                <a href="{{ route('notifications.index') }}" class="drop-foot">{{ __('agencyos.notifications.index.view_all') }}</a>
            </div>
        </div>
        <a class="topbar-avatar" href="{{ route('profile.edit') }}" aria-label="{{ app()->isLocale('ar') ? 'الملف الشخصي' : 'Profile' }}">
            <x-avatar :user="$currentUser"/>
            <span class="presence-dot"></span>
        </a>
    </div>
</div>
<script>
document.addEventListener('keydown', function (e) {
    if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || e.target.getAttribute('contenteditable') === 'true') return;
    var field = document.getElementById('topbarSearch');
    if (field) { e.preventDefault(); field.focus(); }
});

(function () {
    var toggle = document.getElementById('notif-toggle');
    var menu = document.getElementById('notif-menu');
    if (!toggle || !menu) return;

    var badge = document.getElementById('notif-badge');
    var markAllBtn = document.getElementById('notif-mark-all');
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function close() {
        if (menu.classList.contains('hide') || menu.classList.contains('closing')) return;
        toggle.setAttribute('aria-expanded', 'false');
        if (window.__agencyosCloseOpenMenu === close) window.__agencyosCloseOpenMenu = null;
        // Plays the reverse of the open animation before actually hiding — display:none
        // (what .hide does) can't itself be transitioned, so the visible close motion
        // has to finish first, on its own timer, before the menu leaves layout.
        menu.classList.add('closing');
        setTimeout(function () {
            menu.classList.add('hide');
            menu.classList.remove('closing');
        }, 160);
    }
    function open() {
        if (window.__agencyosCloseOpenMenu) window.__agencyosCloseOpenMenu();
        menu.classList.remove('hide', 'closing'); toggle.setAttribute('aria-expanded', 'true');
        window.__agencyosCloseOpenMenu = close;
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.contains('hide') ? open() : close();
    });
    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && e.target !== toggle) close();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });

    // Server-rendered from the SAME $unreadNotificationCount the badge starts at, so
    // this only ever drifts down from what the page loaded with, never out of sync with
    // what's on screen.
    var unread = parseInt((badge && badge.textContent) || '0', 10) || 0;

    function updateBadge() {
        if (!badge) return;
        if (unread > 0) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
        if (markAllBtn) markAllBtn.style.display = unread > 0 ? '' : 'none';
    }

    menu.querySelectorAll('.drop-item[data-url]').forEach(function (item) {
        item.addEventListener('click', function () {
            var wasUnread = item.classList.contains('is-unread');

            fetch(item.dataset.url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            }).then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (wasUnread) {
                        item.classList.remove('is-unread');
                        unread = Math.max(0, unread - 1);
                        updateBadge();
                    }
                    if (data && data.url) {
                        window.location.href = data.url;
                    }
                }).catch(function () {});
        });
    });

    if (markAllBtn) {
        markAllBtn.addEventListener('click', function () {
            fetch(markAllBtn.dataset.url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            }).then(function (res) {
                if (!res.ok) return;
                menu.querySelectorAll('.drop-item.is-unread').forEach(function (item) {
                    item.classList.remove('is-unread');
                });
                unread = 0;
                updateBadge();
            }).catch(function () {});
        });
    }
})();
</script>
