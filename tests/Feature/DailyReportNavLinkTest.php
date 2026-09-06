<?php

namespace Tests\Feature;

use App\Enums\DepartmentSpecialRole;
use App\Models\DepartmentDailyReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** The sidebar's "Daily report" link (layouts/app.blade.php) — dim before today's Daily
 *  Department Report run, orange once it exists, always pointing at today's date — and
 *  the Moderator department's extra "Inbox" link for Content's handoff. */
class DailyReportNavLinkTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_tl_sees_the_dim_nav_link_before_generation(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);

        $response = $this->actingAs($leader)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('nav-pending', false);
        $response->assertDontSee('nav-ready', false);
        $response->assertSee(route('department-reports.show', now()->toDateString()), false);
    }

    public function test_a_manager_sees_the_orange_nav_link_after_generation(): void
    {
        $manager = $this->makeManager();
        DepartmentDailyReport::factory()->create(['report_date' => now()->toDateString()]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('nav-ready', false);
        $response->assertDontSee('nav-pending', false);
    }

    /** A report generated for a different day (e.g. yesterday, not yet cleaned up in a
     *  test scenario) must not make today's link look ready. */
    public function test_a_report_for_a_different_day_does_not_count(): void
    {
        $leader = $this->makeTeamLeader($this->makeDepartment('Marketing'));
        DepartmentDailyReport::factory()->create(['report_date' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($leader)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('nav-pending', false);
        $response->assertDontSee('nav-ready', false);
    }

    public function test_an_employee_never_sees_the_nav_link(): void
    {
        $employee = $this->makeEmployee($this->makeDepartment('Marketing'));

        $response = $this->actingAs($employee)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('department-reports.show', now()->toDateString()), false);
    }

    public function test_an_admin_never_sees_the_nav_link(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee(route('department-reports.show', now()->toDateString()), false);
    }

    private function inboxLabel(): string
    {
        return app()->isLocale('ar') ? 'صندوق الوارد' : 'Inbox';
    }

    public function test_the_moderator_tls_inbox_link_is_dim_before_the_handoff_is_submitted(): void
    {
        $moderator = $this->makeDepartment('Moderation');
        $moderator->update(['special_role' => DepartmentSpecialRole::Moderator]);
        $tl = $this->makeTeamLeader($moderator);

        $response = $this->actingAs($tl)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($this->inboxLabel());
        // Two nav links share the same href (Daily report + Inbox), so this only proves
        // "at least one pending link" — the ready case below is what actually pins the
        // Inbox link's own state to the handoff's submission, not generation having run.
        $response->assertSee('nav-pending', false);
    }

    public function test_the_moderator_tls_inbox_link_turns_ready_once_the_handoff_is_submitted(): void
    {
        $content = $this->makeDepartment('Content');
        $content->update(['special_role' => DepartmentSpecialRole::Content]);
        $contentLeader = $this->makeTeamLeader($content);
        $moderator = $this->makeDepartment('Moderation');
        $moderator->update(['special_role' => DepartmentSpecialRole::Moderator]);
        $tl = $this->makeTeamLeader($moderator);

        DepartmentDailyReport::factory()->moderatorHandoff()->submitted($contentLeader)->create([
            'department_id' => $content->id,
            'report_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($tl)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee($this->inboxLabel());
        $response->assertSee('nav-ready', false);
    }

    /** A normal TL (not Moderator) never gets the Inbox link — only "Daily report". */
    public function test_a_normal_tl_never_sees_the_inbox_link(): void
    {
        $tl = $this->makeTeamLeader($this->makeDepartment('Marketing'));

        $response = $this->actingAs($tl)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee($this->inboxLabel());
    }

    public function test_a_manager_never_sees_the_inbox_link(): void
    {
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee($this->inboxLabel());
    }
}
