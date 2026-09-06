<?php

namespace Tests\Feature;

use App\Enums\DepartmentSpecialRole;
use App\Models\DepartmentDailyReport;
use App\Services\DepartmentReportService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** Daily Department Reports — routes, calendar, day view, and the submit endpoint. */
class DepartmentReportUiTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_calendar_page_renders_for_manager_and_tl(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);

        $this->actingAs($manager)->get(route('department-reports.index'))
            ->assertOk()->assertViewIs('department-reports.index');

        $this->actingAs($leader)->get(route('department-reports.index'))
            ->assertOk();
    }

    /**
     * Product decision (2026-09) — no prev/next-day links and no visible text, just an
     * icon-only calendar link back to the monthly view.
     */
    public function test_the_day_view_has_an_icon_only_link_back_to_the_calendar(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $today = Carbon::parse('2026-09-05');
        Carbon::setTestNow($today);

        $response = $this->actingAs($manager)->get(route('department-reports.show', $today->toDateString()));

        $response->assertOk();
        $response->assertSee(route('department-reports.index', ['month' => $today->format('Y-m')]), false);
        $response->assertDontSee(route('department-reports.show', $today->copy()->subDay()->toDateString()), false);
        $response->assertDontSee(route('department-reports.show', $today->copy()->addDay()->toDateString()), false);
    }

    /** Each department's card gets its own printed page (agencyos.css — .print-page-break). */
    public function test_each_department_card_carries_the_print_page_break_class(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $design = $this->makeDepartment('Design');
        $manager = $this->makeManager();
        $today = now()->toDateString();

        DepartmentDailyReport::factory()->create(['department_id' => $marketing->id, 'report_date' => $today, 'submitted_at' => now()]);
        DepartmentDailyReport::factory()->create(['department_id' => $design->id, 'report_date' => $today, 'submitted_at' => now()]);

        $response = $this->actingAs($manager)->get(route('department-reports.show', $today));

        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'dr-card print-page-break'));
    }

    public function test_an_employee_cannot_reach_department_reports(): void
    {
        $department = $this->makeDepartment('Marketing');
        $employee = $this->makeEmployee($department);

        $this->actingAs($employee)->get(route('department-reports.index'))->assertForbidden();
    }

    /** The sent/received pills read straight off the stored auto_summary snapshot —
     *  no extra query, just counting distinct task_ids by event type. */
    public function test_the_day_view_shows_tasks_sent_and_received_counts(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $today = now()->toDateString();

        DepartmentDailyReport::factory()->create([
            'department_id' => $department->id,
            'report_date' => $today,
            'auto_summary' => [
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 101, 'task_code' => 'TSK-A', 'task_title' => 'Sent task', 'event_types' => ['transferred']],
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 102, 'task_code' => 'TSK-B', 'task_title' => 'Received task 1', 'event_types' => ['sent_to_department']],
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 103, 'task_code' => 'TSK-C', 'task_title' => 'Received task 2', 'event_types' => ['sent_to_department', 'assigned']],
            ],
        ]);

        $response = $this->actingAs($leader)->get(route('department-reports.show', $today));

        $response->assertOk();
        $response->assertSee(__('agencyos.department_reports.show.tasks_sent_label'));
        $response->assertSeeInOrder([__('agencyos.department_reports.show.tasks_sent_label'), '1']);
        $response->assertSee(__('agencyos.department_reports.show.tasks_received_label'));
        $response->assertSeeInOrder([__('agencyos.department_reports.show.tasks_received_label'), '2']);
    }

    public function test_the_day_view_shows_only_what_the_actor_can_see(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $manager = $this->makeManager();
        $today = now()->toDateString();

        $unsubmitted = DepartmentDailyReport::factory()->create(['department_id' => $department->id, 'report_date' => $today]);

        $asLeader = $this->actingAs($leader)->get(route('department-reports.show', $today));
        $asLeader->assertOk();
        $asLeader->assertViewHas('reports', fn ($reports) => $reports->contains('id', $unsubmitted->id));

        $asManager = $this->actingAs($manager)->get(route('department-reports.show', $today));
        $asManager->assertOk();
        $asManager->assertViewHas('reports', fn ($reports) => ! $reports->contains('id', $unsubmitted->id));
    }

    /** Before today's generation run, an empty day means "not made yet," not "nothing
     *  to see" — the TL gets a grayed-out preview of the form they'll fill in later. */
    public function test_todays_report_shows_a_locked_placeholder_before_generation_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31', config('app.timezone'))->setTimeFromTimeString(DepartmentReportService::GENERATION_TIME)->subHour());
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);

        $response = $this->actingAs($leader)->get(route('department-reports.show', now()->toDateString()));

        $response->assertOk();
        $response->assertViewHas('pendingGeneration', true);
        $response->assertSee(__('agencyos.department_reports.show.locked_heading'));
        $response->assertSee(__('agencyos.department_reports.show.locked_placeholder'));
        $response->assertDontSee(__('agencyos.department_reports.show.empty'));
    }

    /** A Manager has no form to fill in, so the locked card shows with no textarea. */
    public function test_a_manager_sees_the_locked_notice_without_a_form(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31', config('app.timezone'))->setTimeFromTimeString(DepartmentReportService::GENERATION_TIME)->subHour());
        $manager = $this->makeManager();

        $response = $this->actingAs($manager)->get(route('department-reports.show', now()->toDateString()));

        $response->assertOk();
        $response->assertSee(__('agencyos.department_reports.show.locked_heading'));
        $response->assertDontSee('<textarea', false);
    }

    /** Once generation time has passed, an empty day is a real gap (or the actor's own
     *  department already submitted so a TL sees nothing) — not "come back later." */
    public function test_an_empty_day_after_generation_time_shows_the_plain_empty_message(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31', config('app.timezone'))->setTimeFromTimeString(DepartmentReportService::GENERATION_TIME)->addHour());
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);

        $response = $this->actingAs($leader)->get(route('department-reports.show', now()->toDateString()));

        $response->assertOk();
        $response->assertViewHas('pendingGeneration', false);
        $response->assertSee(__('agencyos.department_reports.show.empty'));
        $response->assertDontSee(__('agencyos.department_reports.show.locked_heading'));
    }

    /** A past day with nothing generated is a real gap too, regardless of the clock. */
    public function test_a_past_days_empty_report_shows_the_plain_empty_message(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);

        $response = $this->actingAs($leader)->get(route('department-reports.show', now()->subDays(5)->toDateString()));

        $response->assertOk();
        $response->assertSee(__('agencyos.department_reports.show.empty'));
        $response->assertDontSee(__('agencyos.department_reports.show.locked_heading'));
    }

    /** Print is a Manager tool — the report itself already excludes anything unsubmitted
     *  from what a Manager can see, so nothing sensitive ever reaches the printout. */
    /** Product decision 2026-09 — the Moderator's TL gets the same Print button as the
     *  Manager (they now read other departments' approved reports too); an ordinary TL
     *  still doesn't. */
    public function test_the_manager_and_the_moderator_tl_see_the_print_button_but_an_ordinary_tl_does_not(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $manager = $this->makeManager();
        $moderatorDept = $this->makeDepartment('Moderator');
        $moderatorDept->update(['special_role' => DepartmentSpecialRole::Moderator]);
        $moderatorLeader = $this->makeTeamLeader($moderatorDept);
        $today = now()->toDateString();

        DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id, 'report_date' => $today]);
        // Their own department's report is always visible to them (scopeVisibleTo),
        // regardless of submission state — enough for $reports to be non-empty so the
        // Print button's own "isNotEmpty()" guard doesn't hide it in this test.
        DepartmentDailyReport::factory()->create(['department_id' => $moderatorDept->id, 'report_date' => $today]);

        $this->actingAs($manager)->get(route('department-reports.show', $today))
            ->assertSee('onclick="window.print()"', false);

        $this->actingAs($moderatorLeader)->get(route('department-reports.show', $today))
            ->assertSee('onclick="window.print()"', false);

        $this->actingAs($leader)->get(route('department-reports.show', $today))
            ->assertDontSee('onclick="window.print()"', false);
    }

    public function test_the_owning_tl_can_submit_their_report_over_http(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $today = now()->toDateString();
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id, 'report_date' => $today]);

        $this->actingAs($leader)
            ->post(route('department-reports.submit', $report), ['details' => 'All good today.'])
            ->assertRedirect(route('department-reports.show', $today));

        $this->assertTrue($report->fresh()->isSubmitted());
    }

    /** Product decision 2026-09 — the submit form's per-task comments and the general
     *  note are saved and then shown on the report, clearly tied to their own task. */
    public function test_submitting_with_task_comments_and_a_note_renders_them_on_the_show_page(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $today = now()->toDateString();
        $report = DepartmentDailyReport::factory()->create([
            'department_id' => $department->id,
            'report_date' => $today,
            'auto_summary' => [
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 101, 'task_code' => 'TSK-A', 'task_title' => 'Campaign launch', 'event_types' => ['transferred']],
            ],
        ]);

        $this->actingAs($leader)->post(route('department-reports.submit', $report), [
            'details' => 'All good today.',
            'task_comments' => [101 => 'Waiting on client approval.'],
            'external_note' => 'Short-staffed today.',
        ])->assertRedirect(route('department-reports.show', $today));

        $response = $this->actingAs($leader)->get(route('department-reports.show', $today));
        $response->assertSee('Waiting on client approval.');
        $response->assertSee('Short-staffed today.');
    }

    public function test_the_owning_tl_can_edit_their_submitted_report_over_http(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $today = now()->toDateString();
        $report = DepartmentDailyReport::factory()->submitted($leader)->create([
            'department_id' => $department->id,
            'report_date' => $today,
            'details' => 'Original text.',
        ]);

        $this->actingAs($leader)
            ->patch(route('department-reports.update', $report), ['details' => 'Corrected text.'])
            ->assertRedirect(route('department-reports.show', $today));

        $this->assertSame('Corrected text.', $report->fresh()->details);
    }

    public function test_a_manager_can_approve_a_submitted_report_over_http(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);
        $today = now()->toDateString();
        $report = DepartmentDailyReport::factory()->submitted($leader)->create([
            'department_id' => $department->id,
            'report_date' => $today,
        ]);

        $this->actingAs($manager)
            ->post(route('department-reports.approve', $report))
            ->assertRedirect(route('department-reports.show', $today));

        $this->assertTrue($report->fresh()->isApproved());
    }

    /** The Approve button appears only for a Manager, only once submitted, and only
     *  before it's already been approved. */
    public function test_only_the_manager_sees_the_approve_button_and_only_before_its_approved(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);
        $today = now()->toDateString();
        $report = DepartmentDailyReport::factory()->submitted($leader)->create([
            'department_id' => $department->id,
            'report_date' => $today,
        ]);

        $approveUrl = route('department-reports.approve', $report);

        $asLeader = $this->actingAs($leader)->get(route('department-reports.show', $today));
        $asLeader->assertDontSee($approveUrl, false);

        $asManager = $this->actingAs($manager)->get(route('department-reports.show', $today));
        $asManager->assertSee($approveUrl, false);

        app(DepartmentReportService::class)->approve($report, $manager);

        $afterApproval = $this->actingAs($manager)->get(route('department-reports.show', $today));
        $afterApproval->assertDontSee($approveUrl, false);
        $afterApproval->assertSee(__('agencyos.department_reports.show.approved'));
    }

    /** Only the effective leader who can submit can also edit — the Edit button
     *  never appears for a Manager or an unrelated TL. */
    public function test_only_the_owning_tl_sees_the_edit_button(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $manager = $this->makeManager();
        $otherLeader = $this->makeTeamLeader($this->makeDepartment('Design'));
        $today = now()->toDateString();

        DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id, 'report_date' => $today]);

        $this->actingAs($leader)->get(route('department-reports.show', $today))
            ->assertSee(__('agencyos.department_reports.show.edit_button'));

        $this->actingAs($manager)->get(route('department-reports.show', $today))
            ->assertDontSee(__('agencyos.department_reports.show.edit_button'));

        // A different department's TL doesn't see Marketing's report card at all
        // (scopeVisibleTo() excludes it entirely), so the Edit button can't appear.
        $this->actingAs($otherLeader)->get(route('department-reports.show', $today))
            ->assertOk()
            ->assertDontSee(__('agencyos.department_reports.show.edit_button'));
    }

    public function test_editing_an_unsubmitted_report_fails(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $this->actingAs($leader)
            ->patch(route('department-reports.update', $report), ['details' => 'Too early.'])
            ->assertStatus(302);

        $this->assertNull($report->fresh()->details);
    }

    public function test_submitting_without_details_fails_validation(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $this->actingAs($leader)
            ->postJson(route('department-reports.submit', $report), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('details');
    }

    public function test_an_unrelated_tl_is_refused_the_submit_endpoint(): void
    {
        $department = $this->makeDepartment('Marketing');
        $otherDepartment = $this->makeDepartment('Design');
        $otherLeader = $this->makeTeamLeader($otherDepartment);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $this->actingAs($otherLeader)
            ->postJson(route('department-reports.submit', $report), ['details' => 'Not mine.'])
            ->assertForbidden();
    }

    public function test_admin_can_set_a_departments_special_role_from_the_edit_form(): void
    {
        $admin = $this->makeAdmin();
        $department = $this->makeDepartment('Content');

        $this->actingAs($admin)
            ->patch(route('departments.update', $department), [
                'name' => $department->name,
                'special_role' => DepartmentSpecialRole::Content->value,
            ])
            ->assertRedirect(route('departments.index'));

        $this->assertSame(DepartmentSpecialRole::Content, $department->fresh()->special_role);
    }

    /** Notification::index() must resolve every department_report notification's URL in
     *  one batched query, not one per row — mirrors PerformanceRegressionTest's own
     *  small-vs-large comparison style. */
    public function test_notifications_index_resolves_department_report_urls_without_a_query_per_row(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $notifications = app(NotificationService::class);
        $this->actingAs($leader);

        // Each report needs a distinct report_date — (department_id, report_date, type)
        // is the table's own unique index — so a growing $offset walks back through
        // distinct past days instead of colliding on "today" every time.
        $offset = 0;
        $notify = function (int $count) use ($department, $leader, $notifications, &$offset): void {
            for ($i = 0; $i < $count; $i++) {
                $offset++;
                $report = DepartmentDailyReport::factory()->create([
                    'department_id' => $department->id,
                    'report_date' => now()->subDays($offset)->toDateString(),
                ]);
                $notifications->notify($leader, 'department_report.ready', 'Ready', 'Body', 'department_report', $report->id);
            }
        };

        $notify(1);
        // A session's very first request pays a one-off session-bootstrap query later
        // requests on the same session don't — same warm-up PerformanceRegressionTest
        // itself relies on, otherwise this is noise unrelated to the N+1 being tested.
        $this->get(route('notifications.index'))->assertOk();
        $small = $this->queryCountFor(fn () => $this->get(route('notifications.index'))->assertOk());

        $notify(9);
        $large = $this->queryCountFor(fn () => $this->get(route('notifications.index'))->assertOk());

        $this->assertSame($small, $large, 'the notifications page must not fire one extra query per department_report notification');
    }

    private function queryCountFor(callable $request): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $request();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}
