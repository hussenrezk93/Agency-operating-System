<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Jobs\SendInstantNotificationEmailJob;
use App\Models\Client;
use App\Models\Department;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Creating a project has its own instant notification, separate from — and earlier
 * than — the WhatsApp invite ledger, which only activates once a link is set
 * (ProjectInviteNotificationTest). Same recipient audience as that invite fan-out
 * (ProjectWhatsappService::resolveMembers()): the participating departments' active
 * members, the creator, and every active Manager.
 */
class ProjectCreationNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Client $client;

    private Department $marketing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        // created_by explicitly pinned to $this->manager — ClientFactory's own default
        // otherwise manufactures a SECOND, unrelated Manager account (a required FK),
        // which would itself count as a recipient via "every active Manager" and throw
        // off exact-count assertions below.
        $this->client = Client::factory()->create(['created_by' => $this->manager->id]);
        $this->marketing = Department::factory()->create();
    }

    public function test_department_members_the_creator_and_managers_get_an_instant_notification(): void
    {
        Queue::fake();

        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => now()]);
        $otherManager = User::factory()->role(RoleCode::Manager)->create(['email_verified_at' => now()]);

        $this->actingAs($this->manager)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Q3 Campaign',
            'department_ids' => [$this->marketing->id],
        ])->assertCreated();

        foreach ([$employee, $this->manager, $otherManager] as $recipient) {
            $notification = Notification::where('user_id', $recipient->id)->where('type', 'project.created')->firstOrFail();
            $delivery = NotificationDelivery::where('notification_id', $notification->id)->where('channel', 'in_app')->firstOrFail();
            $this->assertSame('sent', $delivery->status->value);
        }

        Queue::assertPushed(SendInstantNotificationEmailJob::class, 3);
    }

    /** Unlike notify(), the email row must never sit `Queued` — see notifyInstant()'s doc. */
    public function test_the_email_is_sent_immediately_not_left_for_the_digest_sweep(): void
    {
        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => now()]);

        $this->actingAs($this->manager)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Q3 Campaign',
            'department_ids' => [$this->marketing->id],
        ])->assertCreated();

        $notification = Notification::where('user_id', $employee->id)->where('type', 'project.created')->firstOrFail();
        $delivery = NotificationDelivery::where('notification_id', $notification->id)->where('channel', 'email')->firstOrFail();

        $this->assertSame('sent', $delivery->status->value);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_an_unverified_recipient_gets_no_email_delivery_row(): void
    {
        User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => null]);

        $this->actingAs($this->manager)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Q3 Campaign',
            'department_ids' => [$this->marketing->id],
        ])->assertCreated();

        $notification = Notification::where('type', 'project.created')->where('user_id', '!=', $this->manager->id)->firstOrFail();
        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'email',
        ]);
    }

    /** Q28-style proof — a mail failure never blocks the request, and is recorded on its own row. */
    public function test_a_mail_failure_is_recorded_without_blocking_the_request(): void
    {
        Mail::shouldReceive('to')->andReturnUsing(function () {
            return new class
            {
                public function send($mailable)
                {
                    throw new \RuntimeException('SMTP host unreachable');
                }
            };
        });

        $this->actingAs($this->manager)->postJson('/projects', [
            'client_id' => $this->client->id,
            'name' => 'Q3 Campaign',
            'department_ids' => [$this->marketing->id],
        ])->assertCreated();

        $notification = Notification::where('user_id', $this->manager->id)->where('type', 'project.created')->firstOrFail();
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'email',
            'status' => 'failed',
        ]);
    }
}
