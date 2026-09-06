<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\DepartmentDailyReport;
use App\Models\Notification;
use App\Models\Task;
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

    /** The title itself is the link to the notification's target on the list page too. */
    public function test_the_notifications_page_makes_a_resolvable_notification_clickable(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $task = Task::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ]);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee(route('notifications.read', $notification), false);
    }

    public function test_the_unread_count_endpoint_reports_only_the_users_own_unread_notifications(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $other = User::factory()->role(RoleCode::Employee)->create();

        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->read()->create(['user_id' => $user->id]);
        Notification::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertExactJson(['count' => 2]);
    }

    /** Clicking a notification should land the user on the thing it's about, not back
     *  on the list — the whole point of this endpoint being a real link now. */
    public function test_marking_a_notification_read_redirects_to_its_target(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $task = Task::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('tasks.show', $task));

        $this->assertTrue($notification->fresh()->is_read);
    }

    /** report_date is cast to a Carbon date on DepartmentDailyReport — targetUrl() must
     *  format it as a plain Y-m-d string before handing it to route(), or Carbon's
     *  default __toString() ("Y-m-d H:i:s") fails the show route's \d{4}-\d{2}-\d{2}
     *  constraint and 404s instead of landing on the report. */
    public function test_marking_a_department_report_notification_read_redirects_to_a_real_url(): void
    {
        $user = User::factory()->role(RoleCode::TeamLeader)->create();
        $report = DepartmentDailyReport::factory()->create(['report_date' => now()->subDays(2)->toDateString()]);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'entity_type' => 'department_report',
            'entity_id' => $report->id,
        ]);

        $this->actingAs($user)
            ->post(route('notifications.read', $notification))
            ->assertRedirect(route('department-reports.show', $report->report_date->toDateString()));
    }

    /** Same bug, batched path: NotificationController::index() pre-resolves report_date
     *  via pluck('report_date', 'id') — also Carbon-cast — into the $reportDates map
     *  passed to targetUrl(). Reproduce that exact call shape directly rather than
     *  through the index page, since the page never renders the resolved URL itself
     *  (the title is a mark-read form, not a direct link — see
     *  test_marking_a_department_report_notification_read_redirects_to_a_real_url for
     *  what actually happens on click). */
    public function test_the_batched_report_dates_map_resolves_to_a_real_url(): void
    {
        $report = DepartmentDailyReport::factory()->create(['report_date' => now()->subDays(3)->toDateString()]);
        $notification = Notification::factory()->create([
            'entity_type' => 'department_report',
            'entity_id' => $report->id,
        ]);

        $reportDates = DepartmentDailyReport::query()->whereKey($report->id)->pluck('report_date', 'id');

        $this->assertSame(
            route('department-reports.show', $report->report_date->toDateString()),
            $notification->targetUrl($reportDates),
        );
    }

    /** No resolvable entity (or none at all) falls back to the notification list. */
    public function test_marking_a_notification_read_without_a_target_redirects_to_the_list(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'entity_type' => null,
            'entity_id' => null,
        ]);

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

    /**
     * The topbar notification dropdown marks-read via fetch(), not a classic form
     * submit — it then navigates to the "url" this JSON response carries.
     */
    public function test_marking_a_notification_read_returns_json_when_requested(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        $task = Task::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'entity_type' => 'task',
            'entity_id' => $task->id,
        ]);
        Notification::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJson(['count' => 1, 'url' => route('tasks.show', $task)]);

        $this->assertTrue($notification->fresh()->is_read);
    }

    public function test_marking_all_read_returns_json_when_requested(): void
    {
        $user = User::factory()->role(RoleCode::Employee)->create();
        Notification::factory()->count(3)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['count' => 0]);

        $this->assertSame(0, $user->unreadNotifications()->count());
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

    /** A verified user with a pending self-service email change gets a reminder too. */
    public function test_the_pending_email_banner_shows_until_the_new_address_is_verified(): void
    {
        $changing = User::factory()->role(RoleCode::Employee)->create([
            'email_verified_at' => now(),
            'pending_email' => 'new.address@dev.local',
        ]);

        $this->actingAs($changing)->get(route('dashboard'))
            ->assertSee(__('agencyos.email_verification.pending_banner_text', ['email' => 'new.address@dev.local']))
            ->assertDontSee(__('agencyos.email_verification.banner_text'));
    }
}
