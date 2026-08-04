<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('employee|127.0.0.1');
        RateLimiter::clear('other|127.0.0.1');
        RateLimiter::clear('employee|10.9.9.9');
    }

    public function test_repeated_failures_are_throttled_and_audited(): void
    {
        User::factory()->create(['username' => 'employee']);

        for ($i = 0; $i < AuthService::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['username' => 'employee', 'password' => 'wrong'])
                ->assertSessionHasErrors('username');
        }

        $this->post('/login', ['username' => 'employee', 'password' => 'wrong']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_throttled']);
        $this->assertGuest();
    }

    public function test_a_different_username_has_its_own_counter(): void
    {
        User::factory()->create(['username' => 'employee']);
        User::factory()->create(['username' => 'other']);

        for ($i = 0; $i < AuthService::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['username' => 'employee', 'password' => 'wrong']);
        }

        // the second account is untouched by the first account's lockout
        $this->post('/login', ['username' => 'other', 'password' => 'Demo123!'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_a_different_ip_has_its_own_counter(): void
    {
        User::factory()->create(['username' => 'employee']);

        for ($i = 0; $i < AuthService::MAX_ATTEMPTS; $i++) {
            $this->post('/login', ['username' => 'employee', 'password' => 'wrong']);
        }

        $this->assertSame(0, RateLimiter::attempts('employee|10.9.9.9'));

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])
            ->post('/login', ['username' => 'employee', 'password' => 'Demo123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_no_password_is_ever_written_to_audit_metadata(): void
    {
        User::factory()->create(['username' => 'employee']);

        $this->post('/login', ['username' => 'employee', 'password' => 'SuperSecret123!']);
        $this->post('/login', ['username' => 'employee', 'password' => 'Demo123!']);

        foreach (AuditLog::all() as $log) {
            $blob = json_encode($log->metadata);
            $this->assertStringNotContainsString('SuperSecret123!', $blob);
            $this->assertStringNotContainsString('Demo123!', $blob);
            $this->assertStringNotContainsString('$2y$', $blob);
        }
    }

    public function test_a_correct_login_clears_the_counter(): void
    {
        User::factory()->create(['username' => 'employee']);

        $this->post('/login', ['username' => 'employee', 'password' => 'wrong']);
        $this->post('/login', ['username' => 'employee', 'password' => 'Demo123!'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertSame(0, RateLimiter::attempts('employee|127.0.0.1'));
    }
}
