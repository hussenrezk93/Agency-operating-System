<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Jobs\SendProjectInviteEmailJob;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectInviteDelivery;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * PHASE 7 — the email leg of the `project_invite_deliveries` ledger, on top of the
 * ledger-writing coverage already in ProjectWhatsappTest.
 */
class ProjectInviteNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Department $marketing;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->manager = User::factory()->role(RoleCode::Manager)->create();
        $this->marketing = Department::factory()->create();
        $this->project = Project::factory()->create(['created_by' => $this->manager->id]);
        $this->project->departments()->attach($this->marketing->id, ['is_active' => true, 'added_at' => now()]);
    }

    public function test_a_verified_members_email_row_dispatches_the_invite_job(): void
    {
        Queue::fake();

        $employee = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => now()]);

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $delivery = ProjectInviteDelivery::where('project_id', $this->project->id)
            ->where('user_id', $employee->id)
            ->where('channel', 'email')
            ->firstOrFail();

        Queue::assertPushed(SendProjectInviteEmailJob::class, fn ($job) => $job->delivery->is($delivery));
    }

    public function test_an_unverified_members_email_row_stays_queued_with_no_job(): void
    {
        Queue::fake();

        $unverified = User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => null]);

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $delivery = ProjectInviteDelivery::where('project_id', $this->project->id)
            ->where('user_id', $unverified->id)
            ->where('channel', 'email')
            ->firstOrFail();

        $this->assertSame('queued', $delivery->status->value);
        Queue::assertNotPushed(SendProjectInviteEmailJob::class, fn ($job) => $job->delivery->is($delivery));
    }

    public function test_a_mail_failure_marks_the_ledger_row_failed_without_blocking_the_request(): void
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

        User::factory()->role(RoleCode::Employee)->inDepartment($this->marketing)->create(['email_verified_at' => now()]);

        $this->actingAs($this->manager)->postJson("/projects/{$this->project->id}/whatsapp", [
            'url' => 'https://chat.whatsapp.com/ABC123',
        ])->assertCreated();

        $this->assertDatabaseHas('project_invite_deliveries', [
            'project_id' => $this->project->id,
            'channel' => 'email',
            'status' => 'failed',
        ]);
    }
}
