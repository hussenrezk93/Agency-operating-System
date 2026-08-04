<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_a_temporary_password_is_redirected_to_the_change_screen(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('password.forced'));
    }

    public function test_the_change_screen_itself_is_reachable_while_forced(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->get('/password/forced')->assertOk();
    }

    public function test_changing_the_password_clears_the_forced_state_and_is_audited(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->post('/password/forced', [
            'current_password' => 'Demo123!',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();

        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('NewSecret123!', $user->password_hash));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.forced_password_changed',
            'actor_user_id' => $user->id,
        ]);
    }

    public function test_the_new_password_must_be_confirmed_and_long_enough(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->post('/password/forced', [
            'current_password' => 'Demo123!',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->actingAs($user)->post('/password/forced', [
            'current_password' => 'not-the-current-one',
            'password' => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($user->refresh()->must_change_password);
    }

    public function test_a_user_who_already_rotated_the_password_is_not_redirected(): void
    {
        $user = User::factory()->create(); // must_change_password = false

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
