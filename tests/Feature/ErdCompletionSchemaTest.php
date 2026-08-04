<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The ERD v1.2 tables completed in Phase 2, proved at the DATABASE level.
 *
 * These assertions deliberately bypass the models and write raw rows. A rule that only
 * holds because a service remembered to apply it is not a schema guarantee; the point of
 * every CHECK and partial unique index below is that a future queue job, console command
 * or hand-written query cannot violate it either.
 */
class ErdCompletionSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function pgsqlOnly(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('These constraints exist only on PostgreSQL.');
        }
    }

    public function test_every_erd_table_exists(): void
    {
        foreach ([
            'notifications', 'notification_deliveries', 'email_verification_tokens',
            'chat_conversations', 'chat_members', 'chat_messages',
            'chat_digest_batches', 'chat_digest_batch_messages',
            'project_whatsapp_link_versions', 'project_invite_deliveries',
            'monthly_performance_snapshots',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "missing ERD table [{$table}]");
        }
    }

    /** BRD §11.1 — one logical event, one delivery row per channel. */
    public function test_a_channel_cannot_be_delivered_twice_for_one_notification(): void
    {
        $this->pgsqlOnly();

        $notificationId = $this->notification();

        DB::table('notification_deliveries')->insert([
            'notification_id' => $notificationId, 'channel' => 'email', 'status' => 'queued',
        ]);

        $this->expectException(QueryException::class);

        DB::table('notification_deliveries')->insert([
            'notification_id' => $notificationId, 'channel' => 'email', 'status' => 'queued',
        ]);
    }

    public function test_a_delivery_marked_sent_must_record_when(): void
    {
        $this->pgsqlOnly();

        $this->expectException(QueryException::class);

        DB::table('notification_deliveries')->insert([
            'notification_id' => $this->notification(),
            'channel' => 'email',
            'status' => 'sent',
            'sent_at' => null,
        ]);
    }

    /**
     * BRD §14 — deleting a chat message removes the CONTENT for everyone. A row that keeps
     * its text while claiming to be deleted must be impossible to store.
     */
    public function test_a_deleted_chat_message_cannot_keep_its_text(): void
    {
        $this->pgsqlOnly();

        $sender = User::factory()->create();
        $conversationId = DB::table('chat_conversations')->insertGetId([
            'type' => 'all_tls', 'department_id' => null, 'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('chat_messages')->insert([
            'conversation_id' => $conversationId,
            'sender_id' => $sender->id,
            'body' => 'this text must not survive deletion',
            'deleted_at' => now(),
            'deleted_by' => $sender->id,
        ]);
    }

    public function test_a_live_chat_message_needs_text_or_a_link(): void
    {
        $this->pgsqlOnly();

        $sender = User::factory()->create();
        $conversationId = DB::table('chat_conversations')->insertGetId([
            'type' => 'all_tls', 'department_id' => null, 'created_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('chat_messages')->insert([
            'conversation_id' => $conversationId,
            'sender_id' => $sender->id,
            'body' => null,
            'link_url' => null,
            'created_at' => now(),
        ]);
    }

    /** A department group names its department; the cross-department types must not. */
    public function test_only_a_department_group_may_name_a_department(): void
    {
        $this->pgsqlOnly();

        $department = Department::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('chat_conversations')->insert([
            'type' => 'all_tls', 'department_id' => $department->id, 'created_at' => now(),
        ]);
    }

    /** BRD §22.18 — an empty two-hour window is never sent. */
    public function test_a_digest_batch_cannot_be_empty(): void
    {
        $this->pgsqlOnly();

        $this->expectException(QueryException::class);

        DB::table('chat_digest_batches')->insert([
            'user_id' => User::factory()->create()->id,
            'window_start' => now()->subHours(2),
            'window_end' => now(),
            'message_count' => 0,
            'status' => 'queued',
        ]);
    }

    /** BRD §22.13 — one invite per member per link version per channel. */
    public function test_a_member_cannot_be_invited_twice_for_the_same_link_version(): void
    {
        $this->pgsqlOnly();

        [$projectId, $versionId] = $this->projectWithLinkVersion();
        $user = User::factory()->create();

        $row = [
            'project_id' => $projectId,
            'user_id' => $user->id,
            'link_version_id' => $versionId,
            'recipient_source' => 'department',
            'channel' => 'email',
            'status' => 'queued',
        ];

        DB::table('project_invite_deliveries')->insert($row);

        $this->expectException(QueryException::class);
        DB::table('project_invite_deliveries')->insert($row);
    }

    public function test_a_project_may_have_only_one_current_link_version(): void
    {
        $this->pgsqlOnly();

        [$projectId] = $this->projectWithLinkVersion();

        $this->expectException(QueryException::class);

        DB::table('project_whatsapp_link_versions')->insert([
            'project_id' => $projectId,
            'version_no' => 2,
            'group_url' => 'https://chat.whatsapp.com/second',
            'action_type' => 'updated',
            'created_by' => User::factory()->create()->id,
            'created_at' => now(),
            'is_current' => true,
        ]);
    }

    /** BRD §17 — no steps due means N/A (NULL), never a zero that drags an average down. */
    public function test_a_month_with_no_due_steps_must_store_null_not_zero(): void
    {
        $this->pgsqlOnly();

        $this->expectException(QueryException::class);

        DB::table('monthly_performance_snapshots')->insert([
            'user_id' => User::factory()->create()->id,
            'month_start' => now()->startOfMonth()->toDateString(),
            'due_steps' => 0,
            'on_time_steps' => 0,
            'overdue_steps' => 0,
            'score' => 0,          // must be NULL when nothing was due
            'snapshot_type' => 'employee',
            'calculated_at' => now(),
        ]);
    }

    public function test_a_department_snapshot_must_name_a_department_not_a_user(): void
    {
        $this->pgsqlOnly();

        $this->expectException(QueryException::class);

        DB::table('monthly_performance_snapshots')->insert([
            'user_id' => User::factory()->create()->id,
            'department_id' => null,
            'month_start' => now()->startOfMonth()->toDateString(),
            'due_steps' => 3,
            'on_time_steps' => 3,
            'overdue_steps' => 0,
            'score' => 100,
            'snapshot_type' => 'department',
            'calculated_at' => now(),
        ]);
    }

    public function test_completed_steps_cannot_exceed_the_steps_that_were_due(): void
    {
        $this->pgsqlOnly();

        $this->expectException(QueryException::class);

        DB::table('monthly_performance_snapshots')->insert([
            'user_id' => User::factory()->create()->id,
            'month_start' => now()->startOfMonth()->toDateString(),
            'due_steps' => 2,
            'on_time_steps' => 3,
            'overdue_steps' => 1,
            'score' => 100,
            'snapshot_type' => 'employee',
            'calculated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------- helpers

    private function notification(): int
    {
        return DB::table('notifications')->insertGetId([
            'user_id' => User::factory()->create()->id,
            'type' => 'task.assigned',
            'title' => 'A task was assigned to you',
            'body' => 'Open My Tasks to begin.',
            'is_read' => false,
            'created_at' => now(),
        ]);
    }

    /** @return array{0: int, 1: int} */
    private function projectWithLinkVersion(): array
    {
        $project = Project::factory()->create();

        $versionId = DB::table('project_whatsapp_link_versions')->insertGetId([
            'project_id' => $project->id,
            'version_no' => 1,
            'group_url' => 'https://chat.whatsapp.com/first',
            'action_type' => 'created',
            'created_by' => User::factory()->create()->id,
            'created_at' => now(),
            'is_current' => true,
        ]);

        return [$project->id, $versionId];
    }
}
