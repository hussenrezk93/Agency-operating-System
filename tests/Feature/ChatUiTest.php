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
