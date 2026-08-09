<?php

namespace Tests\Feature;

use App\Jobs\SendChatDigestEmailJob;
use App\Models\ChatDigestBatch;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 8 — the 2-hour chat digest window and its sweep (BRD §11.1 / §14 / §22.18). */
class ChatDigestTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $leader;

    private User $employee;

    private ChatService $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
        $this->chat = app(ChatService::class);
    }

    public function test_a_message_opens_a_two_hour_batch_for_the_recipient(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->chat->sendMessage($conversation, $this->employee, 'Hello');

        $batch = ChatDigestBatch::where('user_id', $this->leader->id)->firstOrFail();
        $this->assertSame(1, $batch->message_count);
        $this->assertSame('queued', $batch->status->value);
        $this->assertTrue($batch->window_end->equalTo($batch->window_start->clone()->addHours(2)));
    }

    public function test_a_second_message_within_the_window_joins_the_same_batch(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->chat->sendMessage($conversation, $this->employee, 'First');
        $this->chat->sendMessage($conversation, $this->employee, 'Second');

        $this->assertSame(1, ChatDigestBatch::where('user_id', $this->leader->id)->count());
        $batch = ChatDigestBatch::where('user_id', $this->leader->id)->firstOrFail();
        $this->assertSame(2, $batch->message_count);
        $this->assertSame(2, $batch->messages()->count());
    }

    public function test_the_sender_never_gets_a_digest_batch_for_their_own_message(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->chat->sendMessage($conversation, $this->employee, 'Hello');

        $this->assertDatabaseMissing('chat_digest_batches', ['user_id' => $this->employee->id]);
    }

    public function test_the_sender_gets_an_in_app_notification_only_never_a_digest_row(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->chat->sendMessage($conversation, $this->employee, 'Hello');

        $notification = Notification::where('user_id', $this->leader->id)->where('type', 'chat.message')->firstOrFail();
        $this->assertDatabaseMissing('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'email',
        ]);
    }

    public function test_the_sweep_only_closes_batches_whose_window_has_passed(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello');

        // Faked only from here on — sending the message legitimately queues its own
        // job (the live-chat broadcast), which isn't what this assertion is about.
        Queue::fake();

        $this->artisan('agencyos:chat-digests')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_the_sweep_dispatches_a_due_batch(): void
    {
        Queue::fake();
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello');

        ChatDigestBatch::where('user_id', $this->leader->id)->update(['window_start' => now()->subHours(3), 'window_end' => now()->subMinute()]);

        $this->artisan('agencyos:chat-digests')->assertExitCode(0);

        Queue::assertPushed(SendChatDigestEmailJob::class, fn ($job) => $job->batch->user_id === $this->leader->id);
    }

    public function test_a_successful_send_marks_the_batch_sent(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello');
        $batch = ChatDigestBatch::where('user_id', $this->leader->id)->firstOrFail();
        $batch->update(['window_start' => now()->subHours(3), 'window_end' => now()->subMinute()]);

        $this->artisan('agencyos:chat-digests')->assertExitCode(0);

        $this->assertSame('sent', $batch->fresh()->status->value);
    }

    /** Q28-style proof — a mail failure never crashes the sweep, and is recorded on the batch. */
    public function test_a_mail_failure_marks_the_batch_failed_without_crashing_the_sweep(): void
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

        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello');
        $batch = ChatDigestBatch::where('user_id', $this->leader->id)->firstOrFail();
        $batch->update(['window_start' => now()->subHours(3), 'window_end' => now()->subMinute()]);

        $this->artisan('agencyos:chat-digests')->assertExitCode(0);

        $this->assertSame('failed', $batch->fresh()->status->value);
    }

    public function test_a_failed_batch_is_redispatched_by_the_retry_sweep(): void
    {
        Queue::fake();
        $batch = ChatDigestBatch::factory()->failed()->create(['user_id' => $this->leader->id]);

        $this->artisan('agencyos:notification-retry-sweep')->assertExitCode(0);

        Queue::assertPushed(SendChatDigestEmailJob::class, fn ($job) => $job->batch->is($batch));
    }

    /**
     * A Direct message (person-to-person, ConversationType::Direct) skips the 2-hour
     * window entirely: its own one-message batch is dispatched for sending right away,
     * with `window_end` left null so neither the sweep nor a later group message can
     * ever touch it (see NotifyOnChatMessageSent's class doc for why null is safe here).
     */
    public function test_a_direct_message_gets_its_own_batch_sent_immediately(): void
    {
        Queue::fake();
        $other = $this->makeEmployee($this->marketing);
        $conversation = $this->chat->startDirectMessage($this->employee, $other);

        $this->chat->sendMessage($conversation, $this->employee, 'Hey');

        $batch = ChatDigestBatch::where('user_id', $other->id)->firstOrFail();
        $this->assertSame(1, $batch->message_count);
        $this->assertNull($batch->window_end);
        Queue::assertPushed(SendChatDigestEmailJob::class, fn ($job) => $job->batch->is($batch));
    }

    /** The null window_end must never be swept up as if it were "due" or "still open." */
    public function test_a_direct_messages_batch_is_invisible_to_the_sweep_and_never_reused(): void
    {
        $other = $this->makeEmployee($this->marketing);
        $conversation = $this->chat->startDirectMessage($this->employee, $other);
        $this->chat->sendMessage($conversation, $this->employee, 'Hey');

        // Faked only from here on, same as "the sweep only closes..." above — sending
        // the message already dispatched its own (legitimate) instant job.
        Queue::fake();

        $this->artisan('agencyos:chat-digests')->assertExitCode(0);
        Queue::assertNothingPushed();

        // A later, non-Direct message to the same recipient must open its OWN batch,
        // not append to the null-window Direct one.
        $group = $this->chat->resolveDepartmentGroupConversation($this->marketing);
        $this->chat->sendMessage($group, $this->leader, 'unrelated group message');

        $this->assertSame(2, ChatDigestBatch::where('user_id', $other->id)->count());
        $this->assertSame(1, ChatDigestBatch::where('user_id', $other->id)->whereNull('window_end')->count());
        $this->assertSame(1, ChatDigestBatch::where('user_id', $other->id)->whereNotNull('window_end')->count());
    }
}
