<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Notification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PHASE 7 — the real notifications page and the unverified-email banner. */
class NotificationUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_the_notifications_page_renders_only_the_users_own_notifications(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $other = User::factory()->role(RoleCode::Employee)->create();

        $mine = Notification::factory()->create(['user_id' => $user->id, 'title' => 'Mine']);
        Notification::factory()->create(['user_id' => $other->id, 'title' => 'Not mine']);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk()->assertViewIs('notifications.index');
        $response->assertSee('Mine');
        $response->assertDontSee('Not mine');
    }

    public function test_marking_a_notification_read_redirects_back(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_a_user_cannot_mark_someone_elses_notification_read(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $other = User::factory()->role(RoleCode::Employee)->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_the_unverified_email_banner_shows_only_for_an_unverified_user(): void
    {
        $unverified = User::factory()->role(RoleCode::Employee)->create(['email_verified_at' => null]);
        $verified = User::factory()->role(RoleCode::Employee)->create(['email_verified_at' => now()]);

        $this->actingAs($unverified)->get(route('dashboard'))
            ->assertSee(__('agencyos.email_verification.banner_text'));

        $this->actingAs($verified)->get(route('dashboard'))
            ->assertDontSee(__('agencyos.email_verification.banner_text'));
    }
}
