@php
    $currentUser = auth()->user();
    $initials = collect(preg_split('/\s+/u', trim($currentUser?->full_name ?? $currentUser?->username ?? 'U')))->filter()->take(2)->map(fn($p)=>mb_strtoupper(mb_substr($p,0,1)))->implode('');
    $unreadNotificationCount = $currentUser?->unreadNotifications()->count() ?? 0;
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
        <a class="icon-btn" href="{{ route('notifications.index') }}" aria-label="Notifications"><x-icon name="bell" class="ic"/>@if($unreadNotificationCount > 0)<sup>{{ $unreadNotificationCount }}</sup>@endif</a>
        <a class="topbar-avatar" href="{{ route('profile.edit') }}" aria-label="{{ app()->isLocale('ar') ? 'الملف الشخصي' : 'Profile' }}">
            {{ $initials ?: 'U' }}
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
</script>
