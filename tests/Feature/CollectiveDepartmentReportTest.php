<?php

namespace Tests\Feature;

use App\Enums\DepartmentSpecialRole;
use App\Exceptions\DepartmentReportException;
use App\Models\Department;
use App\Models\DepartmentDailyReport;
use App\Models\User;
use App\Services\DepartmentReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/**
 * Product decision 2026-09 — Sales writes its daily report collectively: every member
 * types their own part and the system stacks the parts into the one report the Manager
 * already reads. The rule under test throughout is that the merge is VERBATIM.
 */
class CollectiveDepartmentReportTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private Department $sales;

    private User $leader;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        $this->sales = $this->makeDepartment('Sales');
        $this->sales->update(['special_role' => DepartmentSpecialRole::Sales]);

        $this->leader = $this->makeTeamLeader($this->sales);
        $this->leader->forceFill(['full_name' => 'Zaki Leader'])->save();

        $this->alice = $this->makeEmployee($this->sales);
        $this->alice->forceFill(['full_name' => 'Alice Sales'])->save();

        $this->bob = $this->makeEmployee($this->sales);
        $this->bob->forceFill(['full_name' => 'Bob Sales'])->save();
    }

    private function service(): DepartmentReportService
    {
        return app(DepartmentReportService::class);
    }

    private function report(): DepartmentDailyReport
    {
        $this->service()->generateForToday();

        return DepartmentDailyReport::where('department_id', $this->sales->id)
            ->where('type', 'summary')
            ->firstOrFail();
    }

    // ------------------------------------------------------------------- merge

    /** The heart of it: everybody's words survive exactly as typed, under their name. */
    public function test_the_parts_are_merged_verbatim_under_each_authors_name(): void
    {
        $report = $this->report();

        $this->service()->contribute($report, $this->alice, "Closed 3 deals.\nCalled the Cairo lead twice.");
        $this->service()->contribute($report, $this->bob, 'Followed up on the Alex proposal.');
        $this->service()->contribute($report, $this->leader, 'Reviewed the pipeline.');

        $this->service()->submit($report, $this->leader, 'this text is ignored');

        $details = $report->refresh()->details;

        $this->assertStringContainsString("Closed 3 deals.\nCalled the Cairo lead twice.", $details);
        $this->assertStringContainsString('Followed up on the Alex proposal.', $details);
        $this->assertStringContainsString('Reviewed the pipeline.', $details);
        $this->assertStringContainsString('Alice Sales', $details);
        $this->assertStringNotContainsString('this text is ignored', $details);
    }

    /** Names order the report, so it never reshuffles between one render and the next. */
    public function test_the_parts_are_ordered_by_name(): void
    {
        $report = $this->report();

        $this->service()->contribute($report, $this->bob, 'Bob wrote this.');
        $this->service()->contribute($report, $this->alice, 'Alice wrote this.');
        $this->service()->contribute($report, $this->leader, 'Zaki wrote this.');

        $details = $this->service()->composeDetails($report);

        $this->assertLessThan(strpos($details, 'Bob wrote this.'), strpos($details, 'Alice wrote this.'));
        $this->assertLessThan(strpos($details, 'Zaki wrote this.'), strpos($details, 'Bob wrote this.'));
    }

    /** Asked for 2026-09 — a missing person is named as missing, not quietly dropped. */
    public function test_someone_who_did_not_write_is_shown_as_missing(): void
    {
        $report = $this->report();

        $this->service()->contribute($report, $this->alice, 'Alice wrote this.');
        $this->service()->submit($report, $this->leader, '-');

        $details = $report->refresh()->details;

        $this->assertStringContainsString('Bob Sales', $details);
        $this->assertStringContainsString(__('agencyos.department_reports.contributions.did_not_write'), $details);
    }

    /** The Team Leader submits the report; they do not get to rewrite it. */
    public function test_editing_after_submission_still_rebuilds_from_the_members_parts(): void
    {
        $report = $this->report();
        $this->service()->contribute($report, $this->alice, 'Alice original text.');
        $this->service()->submit($report, $this->leader, '-');

        $this->service()->update($report->refresh(), $this->leader, 'the leader tries to rewrite everything');

        $this->assertStringContainsString('Alice original text.', $report->refresh()->details);
        $this->assertStringNotContainsString('the leader tries to rewrite everything', $report->details);
    }

    /** Every other department keeps writing its report the way it always has. */
    public function test_a_normal_department_still_uses_the_text_it_was_given(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $marketingLeader = $this->makeTeamLeader($marketing);

        $this->service()->generateForToday();
        $report = DepartmentDailyReport::where('department_id', $marketing->id)->firstOrFail();

        $this->service()->submit($report, $marketingLeader, 'The leader wrote this alone.');

        $this->assertSame('The leader wrote this alone.', $report->refresh()->details);
    }

    // ------------------------------------------------------------ writing a part

    public function test_a_member_may_rewrite_their_own_part_until_the_report_is_submitted(): void
    {
        $report = $this->report();

        $this->service()->contribute($report, $this->alice, 'First draft.');
        $this->service()->contribute($report, $this->alice, 'Second draft.');

        $this->assertSame(1, $report->contributions()->count());
        $this->assertStringContainsString('Second draft.', $this->service()->composeDetails($report));
        $this->assertStringNotContainsString('First draft.', $this->service()->composeDetails($report));
    }

    public function test_a_part_cannot_be_written_once_the_report_is_submitted(): void
    {
        $report = $this->report();
        $this->service()->contribute($report, $this->alice, 'Alice wrote this.');
        $this->service()->submit($report, $this->leader, '-');

        $this->expectException(DepartmentReportException::class);
        $this->service()->contribute($report->refresh(), $this->bob, 'Too late.');
    }

    /** Someone from another department has no part to write in this report. */
    public function test_an_outsider_cannot_write_a_part(): void
    {
        $report = $this->report();
        $outsider = $this->makeEmployee($this->makeDepartment('Marketing'));

        $this->expectException(AuthorizationException::class);
        $this->service()->contribute($report, $outsider, 'Not my department.');
    }

    // ------------------------------------------------------------------- screens

    public function test_a_sales_employee_can_open_their_own_report_screen_and_save_a_part(): void
    {
        $report = $this->report();

        $this->actingAs($this->alice)->get(route('department-reports.contribute'))
            ->assertOk()
            ->assertSee(__('agencyos.department_reports.contributions.my_part'), false)
            ->assertSee('Bob Sales', false);

        $this->actingAs($this->alice)
            ->post(route('department-reports.contributions.store', $report), ['body' => 'Two site visits.'])
            ->assertRedirect(route('department-reports.contribute'));

        $this->assertDatabaseHas('department_report_contributions', [
            'report_id' => $report->id,
            'user_id' => $this->alice->id,
            'body' => 'Two site visits.',
        ]);
    }

    /** An employee outside a collective department gets the plain explanation, not a
     *  form they cannot use — and certainly not another department's report. */
    public function test_an_employee_elsewhere_sees_only_an_explanation(): void
    {
        $this->report();
        $outsider = $this->makeEmployee($this->makeDepartment('Marketing'));

        $this->actingAs($outsider)->get(route('department-reports.contribute'))
            ->assertOk()
            ->assertSee(__('agencyos.department_reports.contributions.not_collective'), false)
            ->assertDontSee(__('agencyos.department_reports.contributions.my_part'), false);
    }

    /**
     * The screen has two different "nothing here" answers and they used to be crossed:
     * before the daily generation run, a department that does not write collectively was
     * told its report had not been generated yet. The message must depend on the
     * DEPARTMENT, not on the time of day.
     */
    public function test_the_message_for_another_department_does_not_depend_on_the_hour(): void
    {
        $outsider = $this->makeEmployee($this->makeDepartment('Marketing'));

        foreach (['2026-09-07 09:00:00', '2026-09-07 18:00:00'] as $clock) {
            Carbon::setTestNow($clock);

            $this->actingAs($outsider)->get(route('department-reports.contribute'))
                ->assertOk()
                ->assertSee(__('agencyos.department_reports.contributions.not_collective'), false)
                ->assertDontSee(__('agencyos.department_reports.contributions.not_generated', ['time' => '15:30']));
        }

        Carbon::setTestNow();
    }

    /** A member of a collective department, before the day's report exists. */
    public function test_a_member_is_told_the_report_is_not_open_yet(): void
    {
        Carbon::setTestNow('2026-09-07 09:00:00');

        $this->actingAs($this->alice)->get(route('department-reports.contribute'))
            ->assertOk()
            ->assertSee(__('agencyos.department_reports.contributions.not_generated', ['time' => '15:30']));

        Carbon::setTestNow();
    }

    /** The Team Leader's own screen shows the checklist and a preview of the merge. */
    public function test_the_leader_sees_who_has_written_and_what_will_be_submitted(): void
    {
        $report = $this->report();
        $this->service()->contribute($report, $this->alice, 'Alice wrote this.');

        $response = $this->actingAs($this->leader)->get(route('department-reports.show', now()->toDateString()));

        $response->assertOk();
        $response->assertSee(__('agencyos.department_reports.contributions.preview'), false);
        $response->assertSee('Alice wrote this.', false);
        $response->assertSee(__('agencyos.department_reports.contributions.not_written'), false);
    }
}
