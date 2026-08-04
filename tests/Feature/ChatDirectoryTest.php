<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * The company directory / general "message anyone" feature — a deliberate, later-approved
 * extension of BRD §14's five conversation shapes with a sixth (`direct`) open to everyone,
 * including Admin. See ChatPolicy's class doc for why Admin still can't reach the other five.
 */
class ChatDirectoryTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $marketing;

    private Department $design;

    private User $manager;

    private User $leader;

    private User $employee;

    private ChatService $chat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->marketing = $this->makeDepartment('Marketing');
        $this->design = $this->makeDepartment('Design');
        $this->manager = $this->makeManager();
        $this->leader = $this->makeTeamLeader($this->marketing);
        $this->employee = $this->makeEmployee($this->marketing);
        $this->chat = app(ChatService::class);
    }

    public function test_the_directory_groups_active_users_by_department_with_role_labels(): void
    {
        $directory = $this->chat->directory($this->manager);

        $marketingGroup = $directory['departments']->firstWhere(fn ($g) => $g['department']->id === $this->marketing->id);

        $this->assertNotNull($marketingGroup);
        $this->assertTrue($marketingGroup['members']->contains($this->leader));
        $this->assertTrue($marketingGroup['members']->contains($this->employee));
    }

    public function test_a_disabled_user_does_not_appear_in_the_directory(): void
    {
        $disabled = $this->makeEmployee($this->marketing);
        $disabled->update(['status' => UserStatus::Inactive]);

        $directory = $this->chat->directory($this->manager);

        $marketingGroup = $directory['departments']->firstWhere(fn ($g) => $g['department']->id === $this->marketing->id);

        $this->assertFalse($marketingGroup['members']->contains($disabled));
    }

    public function test_managers_appear_once_in_their_own_section_not_per_department(): void
    {
        $directory = $this->chat->directory($this->employee);

        $this->assertTrue($directory['managers']->contains($this->manager));

        foreach ($directory['departments'] as $group) {
            $this->assertFalse($group['members']->contains($this->manager));
        }
    }

    public function test_admins_appear_in_their_own_section(): void
    {
        $admin = $this->makeAdmin();

        $directory = $this->chat->directory($this->employee);

        $this->assertTrue($directory['admins']->contains($admin));
    }

    public function test_the_actor_never_sees_themself_in_the_directory(): void
    {
        $directory = $this->chat->directory($this->manager);

        $this->assertFalse($directory['managers']->contains($this->manager));
    }

    public function test_any_chat_eligible_role_can_start_a_direct_message_with_any_other(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($this->employee)
            ->post(route('chat.direct-message'), ['user_id' => $admin->id]);

        $response->assertRedirect();
        $this->assertDatabaseHas('chat_conversations', ['type' => 'direct']);
    }

    public function test_starting_a_direct_message_twice_reuses_the_same_conversation(): void
    {
        $first = $this->chat->startDirectMessage($this->employee, $this->manager);
        $second = $this->chat->startDirectMessage($this->employee, $this->manager);

        $this->assertSame($first->id, $second->id);
    }

    public function test_starting_a_direct_message_with_yourself_is_rejected(): void
    {
        $this->actingAs($this->employee)
            ->post(route('chat.direct-message'), ['user_id' => $this->employee->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_an_admin_can_view_a_direct_conversation_they_belong_to(): void
    {
        $admin = $this->makeAdmin();
        $conversation = $this->chat->startDirectMessage($admin, $this->employee);

        $this->actingAs($admin)->get(route('chat.show', $conversation))->assertOk();
    }

    public function test_an_admin_still_cannot_view_a_department_group_conversation(): void
    {
        $admin = $this->makeAdmin();
        $conversation = $this->chat->resolveDepartmentGroupConversation($this->marketing);

        $this->actingAs($admin)->get(route('chat.show', $conversation))->assertForbidden();
    }
}
