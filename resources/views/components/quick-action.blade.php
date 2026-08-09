@php
    $role = auth()->user()?->roleCode()->value ?? 'employee';
    $ar = app()->isLocale('ar');
    // Every entry is a route that already exists and that this role can actually open —
    // never a destination invented for the menu. Employee has no create screens at all,
    // so the button simply doesn't render for that role rather than opening on nothing.
    $actions = match ($role) {
        'admin' => [
            ['route' => route('users.create-form'), 'icon' => 'users', 'label' => $ar ? 'إضافة مستخدم' : 'Add user'],
            ['route' => route('departments.create-form'), 'icon' => 'building-2', 'label' => $ar ? 'إضافة قسم' : 'Add department'],
        ],
        'manager' => [
            ['route' => route('tasks.create-form'), 'icon' => 'list-checks', 'label' => $ar ? 'مهمة جديدة' : 'New task'],
            ['route' => route('projects.create-form'), 'icon' => 'folder-kanban', 'label' => $ar ? 'مشروع جديد' : 'New project'],
            ['route' => route('clients.create-form'), 'icon' => 'star', 'label' => $ar ? 'عميل جديد' : 'New client'],
            ['route' => route('users.create-form'), 'icon' => 'users', 'label' => $ar ? 'إضافة مستخدم' : 'Add user'],
            ['route' => route('departments.create-form'), 'icon' => 'building-2', 'label' => $ar ? 'إضافة قسم' : 'Add department'],
        ],
        'tl' => [
            ['route' => route('tasks.create-form'), 'icon' => 'list-checks', 'label' => $ar ? 'مهمة جديدة' : 'New task'],
            ['route' => route('projects.create-form'), 'icon' => 'folder-kanban', 'label' => $ar ? 'مشروع جديد' : 'New project'],
            ['route' => route('clients.create-form'), 'icon' => 'star', 'label' => $ar ? 'عميل جديد' : 'New client'],
        ],
        default => [],
    };
    $menuId = 'qa-'.\Illuminate\Support\Str::random(8);
@endphp
@if(count($actions))
    <div class="qa-wrap">
        <button type="button" class="btn-quick-action" id="{{ $menuId }}-btn" aria-haspopup="true" aria-expanded="false" aria-controls="{{ $menuId }}-menu">
            <x-icon name="plus" class="ic"/>
            <span>{{ $ar ? 'إجراء سريع' : 'Quick Action' }}</span>
            <x-icon name="chevron-down" class="ic"/>
        </button>
        <div class="qa-menu hide" id="{{ $menuId }}-menu" role="menu">
            @foreach($actions as $action)
                <a class="drop-item" href="{{ $action['route'] }}" role="menuitem"><x-icon :name="$action['icon']"/> <span>{{ $action['label'] }}</span></a>
            @endforeach
        </div>
    </div>
    <script>
    (function () {
        var btn = document.getElementById({{ Illuminate\Support\Js::from($menuId.'-btn') }});
        var menu = document.getElementById({{ Illuminate\Support\Js::from($menuId.'-menu') }});
        if (!btn || !menu) return;

        function close() {
            menu.classList.add('hide');
            btn.setAttribute('aria-expanded', 'false');
            if (window.__agencyosCloseOpenMenu === close) window.__agencyosCloseOpenMenu = null;
        }
        function open() {
            if (window.__agencyosCloseOpenMenu) window.__agencyosCloseOpenMenu();
            menu.classList.remove('hide');
            btn.setAttribute('aria-expanded', 'true');
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
@endif
