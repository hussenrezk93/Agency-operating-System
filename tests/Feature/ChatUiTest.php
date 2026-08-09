<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** PHASE 8 — the real chat screen over the classic browser path. */
class ChatUiTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private User $manager;

    private User $leader;

    private User $employee;

    private ChatService $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
        $this->chat = app(ChatService::class);
    }

    public function test_the_chat_page_renders_for_manager_tl_and_employee(): void
    {
        foreach ([$this->manager, $this->leader, $this->employee] as $actor) {
            $this->actingAs($actor)->get(route('chat.index'))->assertOk()->assertViewIs('chat.index');
        }
    }

    /**
     * all_tls/manager_tls have no distinguishing title of their own — $titleFor()
     * already falls back to the type label ("All Team Leaders"), so the list row must
     * not print that same label a second time underneath as if it were a subtitle. A
     * department_group DOES have a real distinguishing title (the department name), so
     * its type label underneath is genuinely extra information and must still show.
     */
    public function test_a_conversation_with_no_distinct_title_does_not_repeat_its_type_label(): void
    {
        $this->chat->resolveAllTlsConversation();

        $response = $this->actingAs($this->leader)->get(route('chat.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['All Team Leaders'], escape: false);
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'All Team Leaders'),
            'the type label must appear exactly once, not once as the title and again as the subtitle',
        );
    }

    public function test_a_department_group_still_shows_its_type_label_alongside_the_department_name(): void
    {
        $conversation = $this->chat->resolveDepartmentGroupConversation($this->marketing);

        $response = $this->actingAs($this->leader)->get(route('chat.show', $conversation));

        $response->assertOk();
        $response->assertSee($this->marketing->name);
        $response->assertSee('Department group');
    }

    public function test_an_admin_can_open_the_chat_page_but_sees_no_group_conversations(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('chat.index'));

        $response->assertOk()->assertViewIs('chat.index');
        $response->assertViewHas('conversations', fn ($conversations) => $conversations->isEmpty());
        $response->assertViewHas('directory');
    }

    public function test_a_member_can_open_and_see_their_conversation_thread(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->chat->sendMessage($conversation, $this->employee, 'Hello there');

        $response = $this->actingAs($this->leader)->get(route('chat.show', $conversation));

        $response->assertOk();
        $response->assertSee('Hello there');
    }

    public function test_a_non_member_cannot_open_a_conversation(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));

        $this->actingAs($outsider)->get(route('chat.show', $conversation))->assertForbidden();
    }

    public function test_a_classic_send_redirects_back_to_the_thread(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->actingAs($this->employee)
            ->post(route('chat.messages.store', $conversation), ['message' => 'Hello from the classic form'])
            ->assertRedirect(route('chat.show', $conversation));

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Hello from the classic form',
        ]);
    }

    /** The JS compose flow posts with Accept: application/json instead of a classic form submit. */
    public function test_sending_with_accept_json_returns_the_message_payload_instead_of_a_redirect(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $response = $this->actingAs($this->employee)
            ->postJson(route('chat.messages.store', $conversation), ['message' => 'Hello via fetch']);

        $response->assertCreated();
        $response->assertJsonPath('data.body', 'Hello via fetch');
        $response->assertJsonPath('data.sender_name', $this->employee->full_name);
        $response->assertJsonPath('data.is_mine', true);
        $response->assertJsonPath('data.is_deleted', false);
    }

    public function test_polling_returns_only_messages_after_the_given_id(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $first = $this->chat->sendMessage($conversation, $this->employee, 'First message');
        $second = $this->chat->sendMessage($conversation, $this->leader, 'Second message');

        $response = $this->actingAs($this->employee)
            ->getJson(route('chat.poll', $conversation).'?after='.$first->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $second->id);
        $response->assertJsonPath('data.0.body', 'Second message');
    }

    public function test_polling_with_no_new_messages_returns_an_empty_list(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Only message');

        $response = $this->actingAs($this->employee)
            ->getJson(route('chat.poll', $conversation).'?after='.$message->id);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_a_non_member_cannot_poll_a_conversation(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));

        $this->actingAs($outsider)
            ->getJson(route('chat.poll', $conversation).'?after=0')
            ->assertForbidden();
    }

    /**
     * The Pusher JS client's authEndpoint call — same membership rule as chat.show,
     * proven here by hitting /broadcasting/auth exactly like Pusher's own client does.
     */
    public function test_a_member_is_authorized_to_subscribe_to_the_conversations_live_channel(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->actingAs($this->employee)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-chat.conversation.'.$conversation->id,
                'socket_id' => '1234.1234',
            ])
            ->assertOk();
    }

    public function test_a_non_member_is_refused_the_conversations_live_channel(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $outsider = $this->makeEmployee($this->makeDepartment('Design'));

        $this->actingAs($outsider)
            ->post('/broadcasting/auth', [
                'channel_name' => 'private-chat.conversation.'.$conversation->id,
                'socket_id' => '1234.1234',
            ])
            ->assertForbidden();
    }

    public function test_a_classic_delete_redirects_with_a_flash_message(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $message = $this->chat->sendMessage($conversation, $this->employee, 'Oops');

        $this->actingAs($this->employee)
            ->post(route('chat.messages.destroy', $message))
            ->assertRedirect(route('chat.show', $conversation));

        $this->assertNull($message->fresh()->body);
    }

    public function test_a_team_leader_can_start_a_direct_conversation_via_the_classic_form(): void
    {
        $otherLeader = $this->makeTeamLeader($this->makeDepartment('Design'));

        $response = $this->actingAs($this->leader)->post(route('chat.direct'), ['user_id' => $otherLeader->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('chat_conversations', ['type' => 'direct_tl']);
    }

    public function test_an_employee_cannot_start_a_direct_conversation(): void
    {
        $otherLeader = $this->makeTeamLeader($this->makeDepartment('Design'));

        $this->actingAs($this->employee)
            ->post(route('chat.direct'), ['user_id' => $otherLeader->id])
            ->assertForbidden();
    }
}
