<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_login_page(): void
    {
        // Asserted through the translation key, not a hardcoded string: the wording of the
        // login page is design copy and has already changed once. What matters is that the
        // page renders with its real content, not which sentence is on it today.
        $this->get('/login')->assertOk()->assertSee(__('agencyos.auth.submit'));
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create(['username' => 'employee']);

        $this->post('/login', ['username' => 'employee', 'password' => 'Demo123!'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rejected_with_a_wrong_password(): void
    {
        User::factory()->create(['username' => 'employee']);

        $this->post('/login', ['username' => 'employee', 'password' => 'wrong-password'])
            ->assertRedirect()
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_login_is_rejected_for_an_unknown_username(): void
    {
        $this->post('/login', ['username' => 'ghost', 'password' => 'Demo123!'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_invalid_login_message_does_not_reveal_whether_the_username_exists(): void
    {
        User::factory()->create(['username' => 'employee']);

        $this->post('/login', ['username' => 'employee', 'password' => 'nope'])
            ->assertSessionHasErrors('username');
        $wrongPassword = session('errors')->first('username');

        $this->flushSession();

        $this->post('/login', ['username' => 'ghost', 'password' => 'nope'])
            ->assertSessionHasErrors('username');
        $unknownUser = session('errors')->first('username');

        $this->assertSame($wrongPassword, $unknownUser);
        $this->assertStringNotContainsStringIgnoringCase('username does not exist', $wrongPassword);
    }

    public function test_login_requires_both_fields(): void
    {
        $this->post('/login', ['username' => '', 'password' => ''])
            ->assertSessionHasErrors(['username', 'password']);
    }

    public function test_session_id_is_regenerated_after_login(): void
    {
        User::factory()->create(['username' => 'employee']);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['username' => 'employee', 'password' => 'Demo123!']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.logout',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_successful_and_failed_logins_are_audited_without_storing_secrets(): void
    {
        User::factory()->create(['username' => 'employee']);

        $this->post('/login', ['username' => 'employee', 'password' => 'wrong-password']);
        $this->post('/login', ['username' => 'employee', 'password' => 'Demo123!']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_succeeded']);

        foreach (AuditLog::all() as $log) {
            $encoded = json_encode($log->metadata);
            $this->assertStringNotContainsString('Demo123!', $encoded);
            $this->assertStringNotContainsString('wrong-password', $encoded);
            $this->assertStringNotContainsString('$2y$', $encoded); // no bcrypt hashes
        }
    }
}
