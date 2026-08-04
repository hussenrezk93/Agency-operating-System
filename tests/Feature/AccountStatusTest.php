<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['username' => 'disabled.user']);

        $this->post('/login', ['username' => 'disabled.user', 'password' => 'Demo123!'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_blocked']);
    }

    public function test_a_user_deactivated_during_an_active_session_is_signed_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => 'inactive']);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_cannot_reach_authenticated_routes(): void
    {
        foreach (['/dashboard', '/password/forced', '/admin/foundation'] as $route) {
            $this->get($route)->assertRedirect(route('login'));
        }
    }
}
