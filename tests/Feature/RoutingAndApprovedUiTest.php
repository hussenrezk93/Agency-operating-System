<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Routing, authentication boundary and approved-UI authorization.
 *
 * The regression this file exists to prevent: a named route referenced by Blade but never
 * registered. `route()` throws RouteNotFoundException, which surfaces as HTTP 500 on every
 * page that renders the layout — not as a broken link, so it is easy to miss until the
 * whole application is unusable.
 */
class RoutingAndApprovedUiTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, User> */
    private array $demoUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    /**
     * A role-representative account, built fresh per test (not DemoSeeder — that seeder
     * now holds only the two real, named accounts this deployment actually uses). Cached
     * per key within a test so repeat calls return the SAME user, matching how every test
     * below expects to keep acting as "the" manager/tl/employee/admin it already fetched.
     */
    private function demo(string $role): User
    {
        if (isset($this->demoUsers[$role])) {
            return $this->demoUsers[$role];
        }

        $marketing = Department::firstOrCreate(['name' => 'Marketing'], ['is_active' => true]);

        return $this->demoUsers[$role] = match ($role) {
            'admin' => User::factory()->role(RoleCode::Admin)->create(),
            'manager' => User::factory()->role(RoleCode::Manager)->create(),
            'tl' => User::factory()->role(RoleCode::TeamLeader)->inDepartment($marketing)->create(),
            'employee' => User::factory()->role(RoleCode::Employee)->inDepartment($marketing)->create(),
            default => throw new \InvalidArgumentException("Unknown demo role [{$role}]"),
        };
    }

    // ------------------------------------------------------------ root and auth

    public function test_guest_at_the_root_is_sent_to_the_login_route(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_the_login_page_is_reachable_by_a_guest(): void
    {
        $this->get(route('login'))->assertOk();
    }

    /** An authenticated visitor is forwarded on by `guest`, never signed out. */
    public function test_an_authenticated_user_at_the_root_lands_on_the_dashboard(): void
    {
        $manager = $this->demo('manager');

        $this->actingAs($manager)
            ->get('/')
            ->assertRedirect(route('login'));

        $this->actingAs($manager)
            ->get(route('login'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_a_guest_cannot_reach_the_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_guest_cannot_reach_an_approved_ui_screen(): void
    {
        $this->get(route('approved-ui', ['screen' => 'manager-dashboard.html']))
            ->assertRedirect(route('login'));
    }

    public function test_logout_uses_post_and_returns_to_login(): void
    {
        $this->actingAs($this->demo('manager'))
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_approved_ui_url_after_logout_redirects_to_login(): void
    {
        $this->actingAs($this->demo('manager'))->post(route('logout'));

        $this->get(route('approved-ui', ['screen' => 'manager-dashboard.html']))
            ->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------- named routes

    public function test_the_named_routes_the_layout_depends_on_all_resolve(): void
    {
        foreach (['login', 'login.store', 'logout', 'dashboard', 'locale.update', 'approved-ui'] as $name) {
            $this->assertTrue(Route::has($name), "route [{$name}] is not registered");
        }
    }

    /**
     * Every route() name used anywhere in Blade must exist. This is the check that would
     * have caught the missing `approved-ui` registration before it reached a browser.
     */
    public function test_every_route_name_referenced_in_blade_is_registered(): void
    {
        $missing = [];

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);

            // `(?<!->)` excludes request()->route('screen'), which reads a route PARAMETER
            // rather than referencing a named route. Without it the scanner reports a
            // parameter name as a missing route.
            preg_match_all("/(?<!->)route\(\s*'([a-zA-Z0-9_.\-]+)'/", $source, $matches);

            foreach ($matches[1] as $name) {
                if (! Route::has($name)) {
                    $missing[] = basename($file).' → '.$name;
                }
            }
        }

        $this->assertSame([], $missing, 'Blade references unregistered route names');
    }

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    // ------------------------------------------------------- approved UI authorization

    public function test_a_manager_may_open_the_manager_dashboard(): void
    {
        $this->actingAs($this->demo('manager'))
            ->get(route('approved-ui', ['screen' => 'manager-dashboard.html']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_a_manager_may_not_open_the_admin_dashboard(): void
    {
        $this->actingAs($this->demo('manager'))
            ->get(route('approved-ui', ['screen' => 'admin-dashboard.html']))
            ->assertForbidden();
    }

    public function test_a_team_leader_may_open_their_dashboard_and_the_assign_screen(): void
    {
        $tl = $this->demo('tl');

        $this->actingAs($tl)
            ->get(route('approved-ui', ['screen' => 'tl-dashboard.html']))
            ->assertOk();

        $this->actingAs($tl)
            ->get(route('approved-ui', ['screen' => 'task-assign.html']))
            ->assertOk();
    }

    public function test_an_employee_may_open_their_dashboard_but_not_the_assign_screen(): void
    {
        $employee = $this->demo('employee');

        $this->actingAs($employee)
            ->get(route('approved-ui', ['screen' => 'employee-dashboard.html']))
            ->assertOk();

        // Q21 — assignment is the effective Team Leader's, never an employee's.
        $this->actingAs($employee)
            ->get(route('approved-ui', ['screen' => 'task-assign.html']))
            ->assertForbidden();
    }

    /** BRD §15 — the Admin configures and audits; operational task content is not theirs. */
    public function test_an_admin_may_not_open_operational_task_screens(): void
    {
        $admin = $this->demo('admin');

        foreach (['tasks.html', 'task-assign.html', 'task-execute.html', 'tl-review.html'] as $screen) {
            $this->actingAs($admin)
                ->get(route('approved-ui', ['screen' => $screen]))
                ->assertForbidden();
        }

        $this->actingAs($admin)
            ->get(route('approved-ui', ['screen' => 'audit-log.html']))
            ->assertOk();
    }

    /** A role in the query string is decoration; the database decides. */
    public function test_a_role_query_parameter_cannot_change_authorization(): void
    {
        $this->actingAs($this->demo('employee'))
            ->get(route('approved-ui', ['screen' => 'admin-dashboard.html']).'?role=admin')
            ->assertForbidden();
    }

    // -------------------------------------------------------------- screens and assets

    public function test_a_missing_screen_returns_404(): void
    {
        $this->actingAs($this->demo('manager'))
            ->get(route('approved-ui', ['screen' => 'does-not-exist.html']))
            ->assertNotFound();
    }

    public function test_path_traversal_is_refused(): void
    {
        $user = $this->demo('manager');

        foreach ([
            '/app/../.env',
            '/app/..%2F.env',
            '/app/assets/../../views/dashboard.blade.php',
            '/app/dashboard.blade.php',
            '/app/assets/../../../.env',
        ] as $url) {
            $response = $this->actingAs($user)->get($url);

            $this->assertContains(
                $response->getStatusCode(),
                [404, 403, 301, 302],
                "traversal not refused for {$url}",
            );

            if ($response->getStatusCode() === 200) {
                $this->fail("traversal served content for {$url}");
            }
        }
    }

    public function test_prototype_assets_are_served_with_the_right_mime_type(): void
    {
        $user = $this->demo('manager');

        $this->actingAs($user)
            ->get(route('approved-ui', ['screen' => 'assets/guards.js']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');

        $this->actingAs($user)
            ->get(route('approved-ui', ['screen' => 'assets/laravel-bridge.js']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
    }

    public function test_html_screens_are_not_cached(): void
    {
        $this->actingAs($this->demo('manager'))
            ->get(route('approved-ui', ['screen' => 'manager-dashboard.html']))
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /** The public duplicate is gone — there is one canonical prototype source. */
    public function test_the_public_prototype_directory_no_longer_exists(): void
    {
        $this->assertDirectoryDoesNotExist(public_path('prototype'));
        $this->assertFileDoesNotExist(resource_path('views/welcome.blade.php'));
    }

    /** PJAX was removed; its assets must not come back. */
    public function test_the_javascript_router_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('prototype/assets/agencyos-router.js'));
        $this->assertFileDoesNotExist(resource_path('prototype/assets/agencyos-router.css'));

        foreach (glob(resource_path('prototype/*.html')) ?: [] as $screen) {
            $this->assertStringNotContainsString('agencyos-router', file_get_contents($screen));
        }
    }

    // -------------------------------------------------------------------- navigation

    public function test_the_app_root_redirects_to_the_callers_role_home(): void
    {
        $cases = [
            'admin' => 'admin-dashboard.html',
            'manager' => 'manager-dashboard.html',
            'tl' => 'tl-dashboard.html',
            'employee' => 'employee-dashboard.html',
        ];

        foreach ($cases as $username => $home) {
            $this->actingAs($this->demo($username))
                ->get('/app')
                ->assertRedirect(route('approved-ui', ['screen' => $home]));
        }
    }

    /** No loop back to a prototype login: an authenticated caller goes home instead. */
    public function test_the_prototype_login_screen_redirects_home_when_authenticated(): void
    {
        $tl = $this->demo('tl');

        $this->actingAs($tl)
            ->get(route('approved-ui', ['screen' => 'login.html']))
            ->assertRedirect(route('approved-ui', ['screen' => 'tl-dashboard.html']));

        $this->actingAs($tl)
            ->get(route('approved-ui', ['screen' => 'index.html']))
            ->assertRedirect(route('approved-ui', ['screen' => 'tl-dashboard.html']));
    }

    // ------------------------------------------------------------------------ locale

    public function test_switching_locale_returns_to_the_page_it_was_switched_from(): void
    {
        $user = $this->demo('manager');
        $screen = route('approved-ui', ['screen' => 'manager-dashboard.html']);

        $this->actingAs($user)
            ->from($screen)
            ->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect($screen);

        $this->assertSame('ar', session('locale'));
    }

    public function test_an_invalid_locale_is_rejected(): void
    {
        $this->actingAs($this->demo('manager'))
            ->from(route('dashboard'))
            ->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_a_locale_switch_without_a_referer_falls_back_to_the_dashboard(): void
    {
        $this->actingAs($this->demo('manager'))
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect(route('dashboard'));
    }

    // ------------------------------------------------------------------- seeded accounts

    public function test_the_seeded_accounts_can_sign_in_without_a_forced_password_change(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['manager', 'leila.mansour'] as $username) {
            $this->post(route('login.store'), [
                'username' => $username,
                'password' => 'Demo123!',
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticated();
            $this->assertFalse(
                User::where('username', $username)->firstOrFail()->must_change_password,
                "{$username} must not be intercepted by the forced password screen",
            );

            $this->post(route('logout'));
        }
    }

    public function test_the_seeded_team_leader_actually_leads_marketing(): void
    {
        $this->seed(DemoSeeder::class);
        $tl = User::where('username', 'leila.mansour')->firstOrFail();

        $this->assertTrue($tl->hasRole(RoleCode::TeamLeader));
        $this->assertNotNull($tl->department_id);
        $this->assertTrue(
            $tl->canActAsLeaderOf($tl->department_id),
            'the seeded Team Leader must be the EFFECTIVE leader, or no assignment works',
        );
    }
}
