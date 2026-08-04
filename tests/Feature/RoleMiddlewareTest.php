<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The role spine: every foundation route × every role.
 * These routes are deliberately trivial — what is under test is the middleware,
 * not any feature behavior.
 */
class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, RoleCode, int}> */
    public static function matrix(): array
    {
        return [
            'admin route + admin' => ['/admin/foundation', RoleCode::Admin, 200],
            'admin route + manager' => ['/admin/foundation', RoleCode::Manager, 403],
            'admin route + tl' => ['/admin/foundation', RoleCode::TeamLeader, 403],
            'admin route + employee' => ['/admin/foundation', RoleCode::Employee, 403],

            'manager route + manager' => ['/manager/foundation', RoleCode::Manager, 200],
            'manager route + admin' => ['/manager/foundation', RoleCode::Admin, 403],
            'manager route + tl' => ['/manager/foundation', RoleCode::TeamLeader, 403],
            'manager route + employee' => ['/manager/foundation', RoleCode::Employee, 403],

            'tl route + tl' => ['/tl/foundation', RoleCode::TeamLeader, 200],
            'tl route + manager' => ['/tl/foundation', RoleCode::Manager, 200], // multi-role gate
            'tl route + employee' => ['/tl/foundation', RoleCode::Employee, 403],
            'tl route + admin' => ['/tl/foundation', RoleCode::Admin, 403],

            'employee route + employee' => ['/employee/foundation', RoleCode::Employee, 200],
            'employee route + tl' => ['/employee/foundation', RoleCode::TeamLeader, 403],
            'employee route + manager' => ['/employee/foundation', RoleCode::Manager, 403],
            'employee route + admin' => ['/employee/foundation', RoleCode::Admin, 403],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_middleware_allows_and_blocks_correctly(
        string $route,
        RoleCode $role,
        int $expected,
    ): void {
        $user = User::factory()->role($role)->create();

        $this->actingAs($user)->get($route)->assertStatus($expected);
    }

    public function test_role_routes_are_closed_to_guests(): void
    {
        $this->get('/admin/foundation')->assertRedirect(route('login'));
    }
}
