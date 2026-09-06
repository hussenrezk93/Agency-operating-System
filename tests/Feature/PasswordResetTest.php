<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Jobs\SendPasswordResetJob;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * CR-002 (2026-09) — self-service "forgot password": a 2-hour, single-use, hash-stored
 * reset link, mirroring email verification's shape and security posture. The manual
 * Manager/Admin reset (UserController::resetPassword()) is untouched and covered
 * separately by UserManagementTest.php.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $verifiedEmployee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->verifiedEmployee = User::factory()->role(RoleCode::Employee)->create([
            'email_verified_at' => now(),
        ]);
    }

    private function captureResetToken(callable $trigger): ?string
    {
        Queue::fake();

        $trigger();

        $captured = null;
        Queue::assertPushed(SendPasswordResetJob::class, function (SendPasswordResetJob $job) use (&$captured) {
            $captured = Str::afterLast($job->resetUrl, '/');

            return true;
        });

        return $captured;
    }

    public function test_requesting_a_reset_for_a_real_verified_username_issues_a_token_and_queues_the_email(): void
    {
        $token = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );

        $this->assertNotEmpty($token);
        $this->assertDatabaseHas('password_reset_tokens', [
            'user_id' => $this->verifiedEmployee->id,
            'consumed_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_reset_requested']);
    }

    public function test_requesting_a_reset_for_a_nonexistent_username_is_a_silent_no_op(): void
    {
        Queue::fake();

        app(AuthService::class)->requestPasswordReset('no-such-user', request());

        Queue::assertNotPushed(SendPasswordResetJob::class);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_requesting_a_reset_for_a_disabled_account_is_a_silent_no_op(): void
    {
        $disabled = User::factory()->role(RoleCode::Employee)->create([
            'email_verified_at' => now(),
            'status' => UserStatus::Inactive->value,
        ]);
        Queue::fake();

        app(AuthService::class)->requestPasswordReset($disabled->username, request());

        Queue::assertNotPushed(SendPasswordResetJob::class);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_requesting_a_reset_for_an_unverified_email_account_is_a_silent_no_op(): void
    {
        $unverified = User::factory()->role(RoleCode::Employee)->create(['email_verified_at' => null]);
        Queue::fake();

        app(AuthService::class)->requestPasswordReset($unverified->username, request());

        Queue::assertNotPushed(SendPasswordResetJob::class);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_the_forgot_password_form_always_shows_the_same_generic_flash(): void
    {
        $genericFlash = __('agencyos.password_reset.generic_sent_flash');

        $real = $this->post(route('password.forgot.store'), ['username' => $this->verifiedEmployee->username]);
        $real->assertRedirect(route('login'));
        $real->assertSessionHas('status', $genericFlash);

        $fake = $this->post(route('password.forgot.store'), ['username' => 'no-such-user']);
        $fake->assertRedirect(route('login'));
        $fake->assertSessionHas('status', $genericFlash);
    }

    public function test_a_valid_token_resets_the_password_and_the_user_can_sign_in_with_it(): void
    {
        $token = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );

        $this->post(route('password.reset.store', [$this->verifiedEmployee, $token]), [
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('login'));

        $fresh = $this->verifiedEmployee->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertTrue(Hash::check('brand-new-password', $fresh->password_hash));

        $this->post(route('login.store'), [
            'username' => $this->verifiedEmployee->username,
            'password' => 'brand-new-password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $token = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );
        // The `prt_expiry_check` CHECK constraint requires expires_at > created_at, so
        // both must move into the past together to simulate an old, expired token.
        PasswordResetToken::where('user_id', $this->verifiedEmployee->id)
            ->update(['created_at' => now()->subHours(5), 'expires_at' => now()->subHours(3)]);

        $this->expectException(ValidationException::class);

        app(AuthService::class)->resetPassword($this->verifiedEmployee, $token, 'brand-new-password', request());
    }

    public function test_a_consumed_token_cannot_be_reused(): void
    {
        $token = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );
        $this->postResetPassword($token, 'brand-new-password')->assertSessionDoesntHaveErrors();

        $this->postResetPassword($token, 'another-password')->assertSessionHasErrors('token');
    }

    /** A stale second link (still in an inbox or a mail log) must not remain a live
     *  account-takeover path once the user has already regained access with the first. */
    public function test_a_successful_reset_invalidates_every_other_outstanding_token(): void
    {
        $firstToken = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );
        $secondToken = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );

        $this->postResetPassword($firstToken, 'brand-new-password')->assertSessionDoesntHaveErrors();

        $this->postResetPassword($secondToken, 'another-password')->assertSessionHasErrors('token');
    }

    private function postResetPassword(string $token, string $password): TestResponse
    {
        return $this->post(route('password.reset.store', [$this->verifiedEmployee, $token]), [
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /** The account can be disabled in the window between the email being sent and the
     *  link being clicked — the consume step must catch that too, not just issuance. */
    public function test_consuming_a_token_for_a_now_disabled_account_is_rejected(): void
    {
        $token = $this->captureResetToken(
            fn () => app(AuthService::class)->requestPasswordReset($this->verifiedEmployee->username, request()),
        );
        $this->verifiedEmployee->forceFill(['status' => UserStatus::Inactive->value])->save();

        $this->expectException(ValidationException::class);

        app(AuthService::class)->resetPassword($this->verifiedEmployee->fresh(), $token, 'brand-new-password', request());
    }

    /** Proves the ->missing() route wiring: a nonexistent user id must fall back to the
     *  exact same redirect a bad token produces, never a raw 404. */
    public function test_a_nonexistent_user_id_on_the_reset_route_redirects_like_a_bad_token(): void
    {
        $response = $this->get('/password/reset/999999/some-token');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('token');
    }

    public function test_the_forgot_password_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.forgot.store'), ['username' => 'no-such-user']);
        }

        $this->post(route('password.forgot.store'), ['username' => 'no-such-user'])
            ->assertStatus(429);
    }

    public function test_the_reset_consume_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get('/password/reset/999999/some-token');
        }

        $this->get('/password/reset/999999/some-token')->assertStatus(429);
    }
}
