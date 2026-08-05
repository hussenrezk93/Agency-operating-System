<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SeederAndHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_role_seeder_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(4, Role::count());
        foreach (RoleCode::cases() as $code) {
            $this->assertDatabaseHas('roles', ['code' => $code->value]);
        }
    }

    public function test_dev_seeder_creates_the_documented_development_accounts(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = User::where('username', 'manager')->firstOrFail();
        $tl = User::where('username', 'leila.mansour')->firstOrFail();

        $this->assertTrue($admin->hasRole(RoleCode::Admin));
        $this->assertSame('manager@example.com', $admin->personal_email);

        $this->assertTrue($tl->hasRole(RoleCode::TeamLeader));
        $this->assertSame('leila.mansour@example.com', $tl->personal_email);
        $this->assertSame('Marketing', $tl->department?->name);
    }

    /** Rerunning must never duplicate the two accounts or the department. */
    public function test_dev_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertSame(1, Department::where('name', 'Marketing')->count());
    }

    public function test_dev_seeder_refuses_to_run_in_production(): void
    {
        app()['env'] = 'production';

        $this->expectException(RuntimeException::class);

        try {
            (new DemoSeeder)->run();
        } finally {
            app()['env'] = 'testing';
        }
    }

    public function test_seeded_admin_and_team_leader_accounts_use_the_shared_demo_password(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['manager', 'leila.mansour'] as $username) {
            $user = User::where('username', $username)->firstOrFail();

            $this->post('/login', ['username' => $username, 'password' => 'Demo123!'])
                ->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user);

            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }
}
