<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\Department;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * PHASE 8 — the 5 conversation types' find-or-create + lazy member-sync behavior.
 * BRD §14's participant table, one test per row.
 */
class ChatConversationResolutionTest extends TestCase
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

    public function test_employee_tl_conversation_contains_the_employee_and_the_effective_leader(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->assertTrue($conversation->includes($this->employee));
        $this->assertTrue($conversation->includes($this->leader));
        $this->assertSame('employee_tl', $conversation->type->value);
    }

    public function test_resolving_the_same_employee_tl_conversation_twice_does_not_duplicate(): void
    {
        $first = $this->chat->resolveEmployeeTlConversation($this->employee);
        $second = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $first->members()->count());
    }

    public function test_a_temporary_leader_taking_over_is_synced_into_the_employee_tl_conversation(): void
    {
        $conversation = $this->chat->resolveEmployeeTlConversation($this->employee);
        $this->assertFalse($conversation->fresh()->includes($this->manager));

        $tempLeader = $this->appointTemporaryLeader($this->marketing, $this->makeEmployee($this->marketing));

        $resynced = $this->chat->resolveEmployeeTlConversation($this->employee);

        $this->assertTrue($resynced->includes($tempLeader));
        $this->assertTrue($resynced->includes($this->leader), 'the primary leader stays a member even though view-only now');
    }

    public function test_department_group_contains_every_active_employee_and_the_leader(): void
    {
        $conversation = $this->chat->resolveDepartmentGroupConversation($this->marketing);

        $this->assertTrue($conversation->includes($this->employee));
        $this->assertTrue($conversation->includes($this->leader));
        $this->assertSame('department_group', $conversation->type->value);
        $this->assertSame($this->marketing->id, $conversation->department_id);
    }

    public function test_a_new_employee_is_synced_into_the_department_group_on_next_resolve(): void
    {
        $this->chat->resolveDepartmentGroupConversation($this->marketing);

        $newEmployee = $this->makeEmployee($this->marketing);
        $resynced = $this->chat->resolveDepartmentGroupConversation($this->marketing);

        $this->assertTrue($resynced->includes($newEmployee));
    }

    public function test_all_tls_is_a_singleton_containing_every_departments_effective_leader(): void
    {
        $design = $this->makeDepartment('Design');
        $designLeader = $this->makeTeamLeader($design);

        $first = $this->chat->resolveAllTlsConversation();
        $second = $this->chat->resolveAllTlsConversation();

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->includes($this->leader));
        $this->assertTrue($first->fresh()->includes($designLeader));
    }

    public function test_manager_tls_is_a_singleton_containing_managers_and_team_leaders(): void
    {
        $first = $this->chat->resolveManagerTlsConversation();
        $second = $this->chat->resolveManagerTlsConversation();

        $this->assertSame($first->id, $second->id);
        $this->assertTrue($first->includes($this->manager));
        $this->assertTrue($first->includes($this->leader));
        $this->assertFalse($first->includes($this->employee));
    }

    public function test_direct_tl_finds_the_same_conversation_for_either_order_of_the_pair(): void
    {
        $otherLeader = $this->makeTeamLeader($this->makeDepartment('Design'));

        $started = $this->chat->startDirectConversation($this->leader, $otherLeader);
        $again = $this->chat->startDirectConversation($otherLeader, $this->leader);

        $this->assertSame($started->id, $again->id);
        $this->assertSame(2, $started->members()->count());
    }

    public function test_a_team_leader_cannot_start_a_direct_conversation_with_themselves(): void
    {
        $this->expectException(ValidationException::class);

        $this->chat->startDirectConversation($this->leader, $this->leader);
    }

    public function test_a_direct_conversation_cannot_target_a_non_team_leader(): void
    {
        $this->expectException(ValidationException::class);

        $this->chat->startDirectConversation($this->leader, $this->employee);
    }

    public function test_a_manager_cannot_start_a_direct_conversation(): void
    {
        $otherLeader = $this->makeTeamLeader($this->makeDepartment('Design'));

        $this->expectException(AuthorizationException::class);

        $this->chat->startDirectConversation($this->manager, $otherLeader);
    }

    /**
     * Admin can now reach chat for the company directory / general direct messages (a
     * deliberate, later-approved extension), but is structurally never a member of any of
     * the five BRD-restricted group conversation types, so `view()` still denies those.
     */
    public function test_an_admin_can_open_chat_but_never_view_a_restricted_group_conversation(): void
    {
        $admin = $this->makeAdmin();
        $conversation = $this->chat->resolveDepartmentGroupConversation($this->marketing);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', ChatConversation::class));
        $this->assertTrue(Gate::forUser($admin)->denies('view', $conversation));
    }
}
