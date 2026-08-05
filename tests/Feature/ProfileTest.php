<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Jobs\SendEmailVerificationJob;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * BRD §18.1 — "A user can edit their own email from their profile." Every role,
 * self-service only: this route carries no {user} parameter, so there is nothing
 * to authorize beyond being signed in.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_profile_page_renders_for_every_role(): void
    {
        $department = Department::factory()->create();

        foreach ([
            User::factory()->role(RoleCode::Admin)->create(),
            User::factory()->role(RoleCode::Manager)->create(),
            User::factory()->role(RoleCode::TeamLeader)->inDepartment($department)->create(),
            User::factory()->role(RoleCode::Employee)->inDepartment($department)->create(),
        ] as $actor) {
            $this->actingAs($actor)->get('/profile')->assertOk()->assertViewIs('profile.edit');
        }
    }

    public function test_a_user_changes_their_own_email_and_it_goes_to_pending_verification(): void
    {
        Queue::fake();

        $employee = User::factory()->role(RoleCode::Employee)
            ->inDepartment(Department::factory()->create())->create();
        $originalEmail = $employee->personal_email;

        $this->actingAs($employee)
            ->patchJson('/profile', ['personal_email' => 'new.address@dev.local'])
            ->assertOk();

        $fresh = $employee->fresh();
        $this->assertSame($originalEmail, $fresh->personal_email);
        $this->assertSame('new.address@dev.local', $fresh->pending_email);
        Queue::assertPushed(SendEmailVerificationJob::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.updated',
            'entity_type' => 'user',
            'entity_id' => $employee->id,
            'actor_user_id' => $employee->id,
        ]);
    }

    public function test_a_user_cannot_change_their_email_to_one_already_taken(): void
    {
        $department = Department::factory()->create();
        User::factory()->role(RoleCode::Employee)->inDepartment($department)
            ->create(['personal_email' => 'taken@dev.local']);
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($department)->create();

        $this->actingAs($employee)
            ->patchJson('/profile', ['personal_email' => 'taken@dev.local'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('personal_email');
    }

    public function test_a_guest_cannot_reach_the_profile_page(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }
}
