<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Jobs\SendEmailVerificationJob;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * BRD §18.1 — "A user can edit their own email from their profile." Self-view (email +
 * avatar editing) needs nothing beyond being signed in. The optional {user} parameter
 * opens the read-only Activity tab on someone else's profile, gated by the same
 * UserPolicy::viewPerformance() boundary as the performance page — see
 * ProfileActivityTest for that access-control matrix.
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

    public function test_a_user_uploads_a_profile_photo(): void
    {
        Storage::fake('public');

        $employee = User::factory()->role(RoleCode::Employee)
            ->inDepartment(Department::factory()->create())->create();

        $this->actingAs($employee)
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('photo.jpg', 100)->mimeType('image/jpeg')])
            ->assertRedirect(route('profile.edit'));

        $fresh = $employee->fresh();
        $this->assertNotNull($fresh->avatar_path);
        Storage::disk('public')->assertExists($fresh->avatar_path);
        $this->assertNotNull($fresh->avatar_url);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.avatar_updated',
            'entity_type' => 'user',
            'entity_id' => $employee->id,
            'actor_user_id' => $employee->id,
        ]);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $employee = User::factory()->role(RoleCode::Employee)
            ->inDepartment(Department::factory()->create())->create();

        $this->actingAs($employee)
            ->postJson('/profile/avatar', ['avatar' => UploadedFile::fake()->create('resume.pdf', 100)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_a_user_removes_their_profile_photo_and_the_old_file_is_deleted(): void
    {
        Storage::fake('public');

        $employee = User::factory()->role(RoleCode::Employee)
            ->inDepartment(Department::factory()->create())->create();

        $this->actingAs($employee)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('photo.jpg', 100)->mimeType('image/jpeg')]);
        $storedPath = $employee->fresh()->avatar_path;

        $this->actingAs($employee)
            ->delete('/profile/avatar')
            ->assertRedirect(route('profile.edit'));

        $fresh = $employee->fresh();
        $this->assertNull($fresh->avatar_path);
        Storage::disk('public')->assertMissing($storedPath);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.avatar_removed',
            'entity_type' => 'user',
            'entity_id' => $employee->id,
        ]);
    }
}
