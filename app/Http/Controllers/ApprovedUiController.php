<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Serves the approved 51-screen UI through Laravel authentication.
 *
 * The prototype used to live directly under public/prototype, which created a second,
 * browser-only login/session and allowed links to bypass Laravel middleware. It now lives
 * under resources/prototype and is served only through this controller. Every HTML request
 * is checked against the authenticated user's real role before the screen is returned.
 */
class ApprovedUiController extends Controller
{
    private const HOME = [
        'admin' => 'admin-dashboard.html',
        'manager' => 'manager-dashboard.html',
        'tl' => 'tl-dashboard.html',
        'employee' => 'employee-dashboard.html',
    ];

    /** Screens shared by every authenticated role. */
    private const ANY = [
        'profile.html',
        'change-password.html',
        'notifications.html',
        'chat.html',
        'email-verification-success.html',
        'email-verification-expired.html',
        'email-verification-required.html',
        '403.html',
        '404.html',
        '500.html',
        'session-expired.html',
    ];

    /**
     * Server-side screen permissions. This is intentionally stricter than the original
     * static guard: Manager cannot open assignment/execution screens (Q21).
     *
     * @var array<string, array<int, string>>
     */
    private const ACCESS = [
        'admin-dashboard.html' => ['admin'],
        'managers.html' => ['admin'],
        'routing-permissions.html' => ['admin'],
        'output-access-permissions.html' => ['admin'],
        'system-settings.html' => ['admin'],
        'audit-log.html' => ['admin'],

        'manager-dashboard.html' => ['manager'],
        'tl-dashboard.html' => ['tl'],
        'employee-dashboard.html' => ['employee'],

        'users.html' => ['admin', 'manager'],
        'user-create.html' => ['admin', 'manager'],
        'user-edit.html' => ['admin', 'manager'],
        'departments.html' => ['admin', 'manager'],
        'department-create.html' => ['admin', 'manager'],
        'department-edit.html' => ['admin', 'manager'],
        'temporary-tl.html' => ['manager'],
        'temporary-tl-create.html' => ['manager'],

        'clients.html' => ['manager', 'tl'],
        'client-create.html' => ['manager', 'tl'],
        'client-edit.html' => ['manager', 'tl'],
        'client-details.html' => ['manager', 'tl'],

        'projects.html' => ['manager', 'tl', 'employee'],
        'project-create.html' => ['manager', 'tl'],
        'project-edit.html' => ['manager', 'tl'],
        'project-details.html' => ['manager', 'tl', 'employee'],
        'project-whatsapp-history.html' => ['manager', 'tl'],
        'project-invite-deliveries.html' => ['manager', 'tl'],

        'tasks.html' => ['manager', 'tl', 'employee'],
        'task-create.html' => ['manager', 'tl'],
        'task-details.html' => ['manager', 'tl', 'employee'],
        'task-history.html' => ['manager', 'tl', 'employee'],
        'task-assign.html' => ['tl'],
        'task-execute.html' => ['employee', 'tl'],
        'tl-review.html' => ['tl', 'manager'],

        'reports.html' => ['manager', 'tl', 'employee'],
        'employee-performance.html' => ['manager', 'tl', 'employee'],
        'department-performance.html' => ['manager', 'tl'],
    ];

    public function __invoke(Request $request, ?string $screen = null): Response|RedirectResponse
    {
        $user = $request->user();
        $role = $user->roleCode()->value;
        $screen = trim((string) $screen, '/');

        if ($screen === '' || $screen === 'index.html') {
            return redirect()->route('approved-ui', ['screen' => self::HOME[$role]]);
        }

        if (Str::startsWith($screen, 'assets/')) {
            return $this->assetResponse(substr($screen, 7));
        }

        if ($screen === 'login.html' || $screen === 'forced-password-change.html') {
            return redirect()->route('approved-ui', ['screen' => self::HOME[$role]]);
        }

        abort_unless(preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.html\z/', $screen) === 1, 404);

        // ORDER MATTERS. Existence first, permission second, so the two answers stay
        // distinct: 404 means "no such screen", 403 means "it exists and is not yours".
        // With the checks the other way round an unknown filename fell through the ACCESS
        // lookup and was reported as forbidden, which hides typos and broken links behind
        // what looks like a permission problem.
        $path = resource_path('prototype/'.$screen);
        abort_unless(is_file($path), 404);

        abort_unless($this->roleMayOpen($role, $screen), 403);

        $html = file_get_contents($path);
        abort_if($html === false, 500);

        $bridge = $this->bridgeMarkup($request, $role);
        $needle = '<script defer src="assets/guards.js"></script>';

        if (str_contains($html, $needle)) {
            $html = str_replace(
                $needle,
                $bridge."\n".$needle."\n".'<script defer src="assets/laravel-bridge.js"></script>',
                $html,
            );
        } else {
            $html = str_replace('</head>', $bridge."\n".'</head>', $html);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function roleMayOpen(string $role, string $screen): bool
    {
        if (in_array($screen, self::ANY, true)) {
            return true;
        }

        return in_array($role, self::ACCESS[$screen] ?? [], true);
    }

    private function assetResponse(string $relative): Response
    {
        abort_if($relative === '' || str_contains($relative, '..'), 404);
        abort_unless(preg_match('/\A[A-Za-z0-9._\/-]+\z/', $relative) === 1, 404);

        $root = realpath(resource_path('prototype/assets'));
        $path = realpath(resource_path('prototype/assets/'.$relative));

        abort_unless($root !== false && $path !== false && Str::startsWith($path, $root.DIRECTORY_SEPARATOR), 404);
        abort_unless(is_file($path), 404);

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        $contents = file_get_contents($path);
        abort_if($contents === false, 500);

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function bridgeMarkup(Request $request, string $role): string
    {
        $user = $request->user();
        $session = [
            'role' => $role,
            'un' => $user->username,
            'at' => now()->getTimestampMs(),
            'mustChange' => (bool) $user->must_change_password,
        ];

        $config = [
            'role' => $role,
            'username' => $user->username,
            'locale' => app()->getLocale(),
            'logoutUrl' => route('logout'),
            'localeUrl' => route('locale.update'),
            'loginUrl' => route('login'),
            'homeUrl' => route('approved-ui', ['screen' => self::HOME[$role]]),
            'csrf' => csrf_token(),
        ];

        $sessionJson = json_encode($session, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $configJson = json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $localeJson = $this->jsonString(app()->getLocale());

        return <<<HTML
<script>
(function () {
    var session = {$sessionJson};
    localStorage.setItem('agencyos.session.v1', JSON.stringify(session));
    sessionStorage.setItem('agencyos.session.v1', JSON.stringify(session));
    localStorage.setItem('agencyos.lang', {$localeJson});
    window.APP_LARAVEL_BRIDGE = {$configJson};

    var current = new URL(window.location.href);
    if (current.searchParams.has('role')) {
        current.searchParams.delete('role');
        history.replaceState(null, '', current.pathname + (current.search ? current.search : '') + current.hash);
    }
})();
</script>
HTML;
    }

    private function jsonString(string $value): string
    {
        return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }
}
