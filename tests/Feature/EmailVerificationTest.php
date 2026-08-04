<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Jobs\SendEmailVerificationJob;
use App\Models\Department;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Services\EmailVerificationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * BRD §18.1 — a 24h, single-use, hash-stored verification link. Covers both the
 * brand-new-account flow and the pending-email-change flow UserService::updateProfile()
 * now implements (personal_email is never overwritten in place).
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Department $marketing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
    }

    private function captureVerifyToken(callable $trigger): string
    {
        Queue::fake();

        $trigger();

        $captured = null;
        Queue::assertPushed(SendEmailVerificationJob::class, function (SendEmailVerificationJob $job) use (&$captured) {
            $captured = Str::afterLast($job->verifyUrl, '/');

            return true;
        });

        return $captured;
    }

    public function test_creating_an_account_issues_a_verification_token_and_queues_the_email(): void
    {
        $token = $this->captureVerifyToken(function () {
            $this->actingAs($this->manager)->postJson('/users', [
                'full_name' => 'New Employee',
                'username' => 'new.employee',
                'personal_email' => 'new.employee@dev.local',
                'role' => RoleCode::Employee->value,
                'department_id' => $this->marketing->id,
            ])->assertCreated();
        });

        $user = User::where('username', 'new.employee')->firstOrFail();

        $this->assertNotEmpty($token);
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id, 'consumed_at' => null]);
    }

    public function test_the_verify_link_sets_email_verified_at_for_a_brand_new_account(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => null]);
        $token = $this->captureVerifyToken(fn () => app(EmailVerificationService::class)->issueFor($employee, $employee->personal_email));

        $this->get(route('email.verify', [$employee, $token]))->assertRedirect(route('login'));

        $this->assertNotNull($employee->fresh()->email_verified_at);
    }

    public function test_changing_a_verified_users_email_populates_pending_email_without_touching_the_current_one(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create();
        $originalEmail = $employee->personal_email;

        $token = $this->captureVerifyToken(function () use ($employee) {
            $this->actingAs($this->manager)->patchJson("/users/{$employee->id}", [
                'personal_email' => 'brand.new@dev.local',
            ])->assertOk();
        });

        $fresh = $employee->fresh();
        $this->assertSame($originalEmail, $fresh->personal_email, 'the old, verified address must stay in place');
        $this->assertSame('brand.new@dev.local', $fresh->pending_email);
        $this->assertNotNull($fresh->email_verified_at, 'the account is still verified via the OLD address');

        $this->get(route('email.verify', [$employee, $token]))->assertRedirect();

        $promoted = $employee->fresh();
        $this->assertSame('brand.new@dev.local', $promoted->personal_email);
        $this->assertNull($promoted->pending_email);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => null]);
        $token = $this->captureVerifyToken(fn () => app(EmailVerificationService::class)->issueFor($employee, $employee->personal_email));
        // The `evt_expiry_check` CHECK constraint requires expires_at > created_at, so
        // both must move into the past together to simulate an old, expired token.
        EmailVerificationToken::where('user_id', $employee->id)
            ->update(['created_at' => now()->subDays(2), 'expires_at' => now()->subDay()]);

        $this->expectException(ValidationException::class);

        app(EmailVerificationService::class)->consume($employee, $token);
    }

    public function test_a_consumed_token_cannot_be_reused(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => null]);
        $token = $this->captureVerifyToken(fn () => app(EmailVerificationService::class)->issueFor($employee, $employee->personal_email));
        app(EmailVerificationService::class)->consume($employee, $token);

        $this->expectException(ValidationException::class);

        app(EmailVerificationService::class)->consume($employee, $token);
    }

    public function test_a_mismatched_token_is_rejected(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => null]);
        $this->captureVerifyToken(fn () => app(EmailVerificationService::class)->issueFor($employee, $employee->personal_email));

        $this->expectException(ValidationException::class);

        app(EmailVerificationService::class)->consume($employee, 'not-the-real-token');
    }
}
