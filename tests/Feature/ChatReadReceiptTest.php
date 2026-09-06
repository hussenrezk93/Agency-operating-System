<?php

namespace Tests\Feature;

use App\Events\ChatReadReceiptBroadcast;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** Read receipts ("seen" ticks + auto-clearing the matching chat notification). */
class ChatReadReceiptTest extends TestCase
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

    public function test_marking_read_advances_the_readers_watermark(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $this->chat->markRead($conversation, $this->leader);

        $member = $conversation->activeMembers()->where('user_id', $this->leader->id)->first();
        $this->assertSame($message->id, $member->last_read_message_id);
        $this->assertNotNull($member->last_read_at);
    }

    public function test_marking_read_broadcasts_the_new_watermark_on_the_conversations_channel(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $this->chat->markRead($conversation, $this->leader);

        Event::assertDispatched(ChatReadReceiptBroadcast::class, function (ChatReadReceiptBroadcast $event) use ($conversation, $message) {
            $channels = $event->broadcastOn();

            return $event->userId === $this->leader->id
                && $event->lastReadMessageId === $message->id
                && $event->broadcastAs() === 'message.read'
                && count($channels) === 1
                && $channels[0]->name === 'private-chat.conversation.'.$conversation->id;
        });
    }

    public function test_marking_read_with_nothing_new_does_not_rebroadcast(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $this->chat->markRead($conversation, $this->leader);
        $this->chat->markRead($conversation, $this->leader);

        Event::assertDispatchedTimes(ChatReadReceiptBroadcast::class, 1);
    }

    public function test_marking_read_clears_the_matching_chat_notification_but_not_others(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $chatNotification = Notification::where('user_id', $this->leader->id)
            ->where('type', 'chat.message')
            ->where('entity_id', $conversation->id)
            ->firstOrFail();
        $unrelated = Notification::factory()->create(['user_id' => $this->leader->id, 'type' => 'task.assigned']);

        $this->chat->markRead($conversation, $this->leader);

        $this->assertTrue($chatNotification->fresh()->is_read);
        $this->assertFalse($unrelated->fresh()->is_read);
    }

    public function test_a_non_member_cannot_mark_a_conversation_read(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello there');
        $outsider = $this->makeEmployee($this->makeDepartment('Sales'));

        $this->actingAs($outsider)
            ->post(route('chat.read', $conversation))
            ->assertForbidden();
    }

    public function test_marking_read_over_http_returns_ok(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $this->actingAs($this->leader)
            ->postJson(route('chat.read', $conversation))
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $member = $conversation->activeMembers()->where('user_id', $this->leader->id)->first();
        $this->assertSame($message->id, $member->last_read_message_id);
    }

    public function test_unread_count_counts_only_the_other_sides_messages_since_my_watermark(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'One');
        $this->chat->sendMessage($conversation, $this->leader, 'My own — should not count');
        $this->chat->sendMessage($conversation, $this->employee, 'Two');

        $counts = $this->chat->unreadCountsFor($this->leader, collect([$conversation]));

        $this->assertSame(2, $counts[$conversation->id]);
    }

    public function test_unread_count_drops_to_zero_after_marking_read(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $this->chat->markRead($conversation, $this->leader);
        $counts = $this->chat->unreadCountsFor($this->leader, collect([$conversation]));

        $this->assertSame(0, $counts[$conversation->id]);
    }

    /** department_group is the one conversation type both an Employee and their TL always share in conversationsFor(). */
    public function test_opening_a_conversation_over_http_clears_its_own_unread_badge(): void
    {
        $conversation = $this->chat->resolveDepartmentGroupConversation($this->marketing);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $response = $this->actingAs($this->leader)->get(route('chat.show', $conversation));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('unreadCounts')[$conversation->id]);
    }
}
