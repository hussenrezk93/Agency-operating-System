<?php

namespace Tests\Feature;

use App\Events\ChatMessageBroadcast;
use App\Events\ChatMessageDeletedBroadcast;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 8 — sending and deleting chat messages (BRD §14). */
class ChatMessagingTest extends TestCase
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

    public function test_plain_text_is_stored_as_body(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $message = $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $this->assertSame('Hello there', $message->body);
        $this->assertNull($message->link_url);
    }

    public function test_a_pasted_https_link_is_stored_as_link_url(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $message = $this->chat->sendMessage($conversation, $this->employee, 'https://drive.example.com/brief');

        $this->assertNull($message->body);
        $this->assertSame('https://drive.example.com/brief', $message->link_url);
    }

    public function test_an_http_only_link_is_treated_as_plain_text(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $message = $this->chat->sendMessage($conversation, $this->employee, 'http://insecure.example.com');

        $this->assertSame('http://insecure.example.com', $message->body);
        $this->assertNull($message->link_url);
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->expectException(ValidationException::class);

        $this->chat->sendMessage($conversation, $this->employee, '   ');
    }

    public function test_a_non_member_cannot_send(): void
    {
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->expectException(AuthorizationException::class);

        $this->chat->sendMessage($conversation, $outsider, 'Not my conversation');
    }

    public function test_deleting_clears_the_content_for_everyone(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Please review this');

        $this->chat->deleteMessage($message, $this->employee);

        $fresh = $message->fresh();
        $this->assertNull($fresh->body);
        $this->assertNull($fresh->link_url);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame($this->employee->id, $fresh->deleted_by);
    }

    public function test_only_the_sender_may_delete_their_message_not_even_the_department_leader(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Please review this');

        $this->expectException(AuthorizationException::class);

        $this->chat->deleteMessage($message, $this->leader);
    }

    public function test_a_deleted_message_cannot_be_deleted_again(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Please review this');
        $this->chat->deleteMessage($message, $this->employee);

        $this->expectException(AuthorizationException::class);

        $this->chat->deleteMessage($message->fresh(), $this->employee);
    }

    /** Both broadcast events are already faked app-wide by Tests\TestCase — see its setUp(). */
    public function test_sending_a_message_broadcasts_it_on_the_conversations_private_channel(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $message = $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        Event::assertDispatched(ChatMessageBroadcast::class, function (ChatMessageBroadcast $event) use ($conversation, $message) {
            $channels = $event->broadcastOn();

            return $event->message->is($message)
                && $event->broadcastAs() === 'message.new'
                && count($channels) === 1
                && $channels[0]->name === 'private-chat.conversation.'.$conversation->id;
        });
    }

    public function test_deleting_a_message_broadcasts_the_deletion(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Oops, wrong channel');

        $this->chat->deleteMessage($message, $this->employee);

        Event::assertDispatched(ChatMessageDeletedBroadcast::class, fn (ChatMessageDeletedBroadcast $event) => $event->message->is($message)
            && $event->broadcastAs() === 'message.deleted');
    }

    public function test_the_audit_log_never_stores_the_message_text(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Something sensitive');
        $this->chat->deleteMessage($message, $this->employee);

        $this->assertDatabaseMissing('audit_logs', ['metadata->after->body' => 'Something sensitive']);
        foreach (AuditLog::whereIn('action', ['chat.message_sent', 'chat.message_deleted'])->get() as $log) {
            $this->assertStringNotContainsString('Something sensitive', json_encode($log->metadata));
        }
    }
}
