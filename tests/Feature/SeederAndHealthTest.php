<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Enums\WorkflowStatus;
use App\Models\Department;
use App\Models\Role;
use App\Models\Task;
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

    public function test_the_demo_seeder_builds_an_account_for_every_role(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (RoleCode::cases() as $code) {
            $this->assertTrue(
                User::whereHas('role', fn ($q) => $q->where('code', $code->value))->exists(),
                "the demo workspace has nobody with the {$code->value} role",
            );
        }

        $this->assertTrue(User::where('username', 'manager')->firstOrFail()->hasRole(RoleCode::Manager));
        $this->assertSame('Graphic', User::where('username', 'leila.mansour')->firstOrFail()->department?->name);
    }

    /**
     * The whole point of the demo data is that a fresh install has something to look
     * at, so the workspace must come up populated rather than merely valid.
     */
    public function test_the_demo_seeder_fills_the_workspace_with_work_to_look_at(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThan(5, Task::count());
        $this->assertGreaterThan(1, Department::count());

        // Every review stage represented at once, which is what makes the screens
        // worth opening: a queue, work in flight, and something waiting at each gate.
        foreach ([
            WorkflowStatus::WaitingAssignment,
            WorkflowStatus::InProgress,
            WorkflowStatus::UnderReview,
            WorkflowStatus::PendingContentReview,
            WorkflowStatus::PendingManagerReview,
            WorkflowStatus::ChangesRequested,
        ] as $status) {
            $this->assertDatabaseHas('task_steps', ['workflow_status' => $status->value]);
        }
    }

    /** Rerunning must not duplicate the workspace. */
    public function test_the_demo_seeder_is_idempotent(): void
    {
        $this->seed(DemoSeeder::class);
        $users = User::count();
        $tasks = Task::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($users, User::count());
        $this->assertSame($tasks, Task::count());
    }

    public function test_the_demo_seeder_refuses_to_run_in_production(): void
    {
        app()['env'] = 'production';

        $this->expectException(RuntimeException::class);

        try {
            (new DemoSeeder)->run();
        } finally {
            app()['env'] = 'testing';
        }
    }

    public function test_every_demo_account_signs_in_with_the_documented_password(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['manager', 'admin', 'leila.mansour', 'salma.fouad'] as $username) {
            $user = User::where('username', $username)->firstOrFail();

            $this->post('/login', ['username' => $username, 'password' => 'demo123'])
                ->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user);

            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }
}
