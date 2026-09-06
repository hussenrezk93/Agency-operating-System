<?php

namespace Tests\Feature;

use App\Enums\DepartmentSpecialRole;
use App\Exceptions\DepartmentReportException;
use App\Models\Department;
use App\Models\DepartmentDailyReport;
use App\Services\DepartmentReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkflowScenarios;
use Tests\TestCase;

/** Daily Department Reports — generation, deadlines, submission, and visibility. */
class DepartmentReportTest extends TestCase
{
    use BuildsWorkflowScenarios;
    use RefreshDatabase;

    private function service(): DepartmentReportService
    {
        return app(DepartmentReportService::class);
    }

    public function test_generate_for_today_creates_one_summary_report_per_active_department(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $design = $this->makeDepartment('Design');
        $inactive = Department::factory()->create(['is_active' => false]);

        $this->service()->generateForToday();

        $this->assertDatabaseHas('department_daily_reports', ['department_id' => $marketing->id, 'type' => 'summary']);
        $this->assertDatabaseHas('department_daily_reports', ['department_id' => $design->id, 'type' => 'summary']);
        $this->assertDatabaseMissing('department_daily_reports', ['department_id' => $inactive->id]);
    }

    public function test_generate_for_today_also_creates_a_moderator_handoff_report_for_content_only(): void
    {
        $content = $this->makeDepartment('Content');
        $content->update(['special_role' => DepartmentSpecialRole::Content]);
        $other = $this->makeDepartment('Marketing');

        $this->service()->generateForToday();

        $this->assertSame(2, DepartmentDailyReport::where('department_id', $content->id)->count());
        $this->assertSame(1, DepartmentDailyReport::where('department_id', $other->id)->count());
        $this->assertDatabaseHas('department_daily_reports', [
            'department_id' => $content->id,
            'type' => 'moderator_handoff',
        ]);
        $this->assertNull(
            DepartmentDailyReport::where('department_id', $content->id)->where('type', 'moderator_handoff')->first()->auto_summary
        );
    }

    public function test_running_generate_for_today_twice_is_idempotent(): void
    {
        $this->makeDepartment('Marketing');

        $this->service()->generateForToday();
        $afterFirst = DepartmentDailyReport::count();

        $this->service()->generateForToday();
        $afterSecond = DepartmentDailyReport::count();

        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_generate_for_today_notifies_the_departments_effective_leader(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);

        $this->service()->generateForToday();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $leader->id,
            'type' => 'department_report.ready',
        ]);
    }

    public function test_auto_summary_aggregates_todays_task_activity_for_the_department(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $employee = $this->makeEmployee($department);
        [$task] = $this->taskInProgress($department, $leader, $employee);

        $this->service()->generateForToday();

        $report = DepartmentDailyReport::where('department_id', $department->id)->where('type', 'summary')->first();
        $this->assertNotEmpty($report->auto_summary);
        $this->assertTrue(collect($report->auto_summary)->contains('task_id', $task->id));
    }

    /** A task with several activity rows in the same day (e.g. assigned then submitted)
     *  must still get exactly one comment box, not one per row. */
    public function test_distinct_summary_tasks_deduplicates_by_task_id(): void
    {
        $leader = $this->makeTeamLeader($this->makeDepartment('Marketing'));
        $report = DepartmentDailyReport::factory()->create([
            'auto_summary' => [
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 101, 'task_code' => 'TSK-A', 'task_title' => 'Campaign launch', 'event_types' => ['assigned']],
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 101, 'task_code' => 'TSK-A', 'task_title' => 'Campaign launch', 'event_types' => ['submitted']],
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 102, 'task_code' => 'TSK-B', 'task_title' => 'Promo video', 'event_types' => ['assigned']],
            ],
        ]);

        $this->assertCount(2, $report->distinctSummaryTasks());
    }

    /** TaskWorkflowService::sendToNextDepartment() writes 'transferred' on the OLD
     *  step's department and 'sent_to_department' on the new one at the same moment —
     *  each side's report should count the same handoff as "sent" or "received". */
    public function test_auto_summary_tracks_tasks_sent_and_received_between_departments(): void
    {
        $sending = $this->makeDepartment('Marketing');
        $receiving = $this->makeDepartment('Design');
        $this->allowRoute($sending, $receiving);
        $leader = $this->makeTeamLeader($sending);
        $employee = $this->makeEmployee($sending);
        [$task, $step] = $this->taskApproved($sending, $leader, $employee);

        // Push creation/approval history to yesterday so only today's transfer pair
        // ('transferred' on $sending, 'sent_to_department' on $receiving) is in scope —
        // otherwise $sending would also show a "received" count of 1 from its own
        // creation-day 'sent_to_department' event, which is correct but not what this
        // test is isolating (see test_a_newly_created_task_counts_as_received... below).
        $task->history()->update(['created_at' => now()->subDay()]);

        $this->workflow()->sendToNextDepartment($step, $leader, $receiving);

        $this->service()->generateForToday();

        $sentReport = DepartmentDailyReport::where('department_id', $sending->id)->where('type', 'summary')->first();
        $receivedReport = DepartmentDailyReport::where('department_id', $receiving->id)->where('type', 'summary')->first();

        $this->assertSame(1, $sentReport->sentTasksCount());
        $this->assertSame(0, $sentReport->receivedTasksCount());
        $this->assertSame(0, $receivedReport->sentTasksCount());
        $this->assertSame(1, $receivedReport->receivedTasksCount());
    }

    /** A brand-new task routed to its first department counts as "received" there too —
     *  createTask() writes the same 'sent_to_department' event as a mid-workflow transfer. */
    public function test_a_newly_created_task_counts_as_received_by_its_first_department(): void
    {
        $department = $this->makeDepartment('Marketing');
        $this->newTask($department, $this->makeManager());

        $this->service()->generateForToday();

        $report = DepartmentDailyReport::where('department_id', $department->id)->where('type', 'summary')->first();
        $this->assertSame(1, $report->receivedTasksCount());
        $this->assertSame(0, $report->sentTasksCount());
    }

    /** Q11/Q14 — while a temporary TL covers the department, they are the effective
     *  leader, so they receive the daily report notification instead of the primary TL,
     *  who keeps the assignment but loses authority for the period. */
    public function test_generate_for_today_notifies_the_temporary_leader_instead_of_the_primary(): void
    {
        $department = $this->makeDepartment('Marketing');
        $primary = $this->makeTeamLeader($department);
        $employee = $this->makeEmployee($department);
        $this->appointTemporaryLeader($department, $employee);

        $this->service()->generateForToday();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'type' => 'department_report.ready',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $primary->id,
            'type' => 'department_report.ready',
        ]);
    }

    /** Product decision 2026-09 — every department's deadline is now end-of-day, not
     *  just Photography/Videography's; the 20:00 cutoff for everyone else is gone. */
    public function test_deadline_is_end_of_day_for_every_department(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $photography = $this->makeDepartment('Photography');
        $photography->update(['special_role' => DepartmentSpecialRole::PhotographyVideography]);

        $today = now()->toDateString();

        $this->assertSame(
            "{$today} 23:59:59",
            $this->service()->deadlineFor($marketing, $today)->toDateTimeString(),
        );
        $this->assertSame(
            "{$today} 23:59:59",
            $this->service()->deadlineFor($photography, $today)->toDateTimeString(),
        );
    }

    public function test_the_owning_departments_effective_leader_can_submit_the_report(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $submitted = $this->service()->submit($report, $leader, 'Everything on track.');

        $this->assertTrue($submitted->isSubmitted());
        $this->assertSame('Everything on track.', $submitted->details);
        $this->assertSame($leader->id, $submitted->submitted_by);
        $this->assertFalse($submitted->is_late);
    }

    /** Product decision 2026-09 — a TL can attach a comment to each task in the day's
     *  activity table, keyed by task_id so the Manager sees which task it belongs to,
     *  plus one general note not tied to any task. */
    public function test_submitting_stores_task_comments_and_an_external_note(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create([
            'department_id' => $department->id,
            'auto_summary' => [
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 101, 'task_code' => 'TSK-A', 'task_title' => 'Campaign launch', 'event_types' => ['transferred']],
            ],
        ]);

        $submitted = $this->service()->submit(
            $report,
            $leader,
            'Everything on track.',
            [101 => 'Waiting on client approval before final cut.'],
            'Office was short-staffed today.',
        );

        $this->assertSame('Waiting on client approval before final cut.', $submitted->commentForTask(101));
        $this->assertSame('Office was short-staffed today.', $submitted->external_note);
    }

    /** Blank comments/notes are never stored — nothing to show, nothing to store. */
    public function test_blank_task_comments_and_notes_are_not_stored(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create([
            'department_id' => $department->id,
            'auto_summary' => [
                ['user_id' => $leader->id, 'user_name' => $leader->full_name, 'task_id' => 101, 'task_code' => 'TSK-A', 'task_title' => 'Campaign launch', 'event_types' => ['transferred']],
            ],
        ]);

        $submitted = $this->service()->submit($report, $leader, 'Everything on track.', [101 => '   '], '  ');

        $this->assertNull($submitted->commentForTask(101));
        $this->assertNull($submitted->external_note);
        $this->assertSame([], $submitted->task_comments);
    }

    public function test_editing_a_submitted_report_can_change_task_comments_and_the_note(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->submitted($leader)->create([
            'department_id' => $department->id,
            'task_comments' => [101 => 'Original comment.'],
            'external_note' => 'Original note.',
        ]);

        $updated = $this->service()->update($report, $leader, 'Corrected text.', [101 => 'Updated comment.'], 'Updated note.');

        $this->assertSame('Updated comment.', $updated->commentForTask(101));
        $this->assertSame('Updated note.', $updated->external_note);
    }

    public function test_submitting_after_the_deadline_is_flagged_late(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create([
            'department_id' => $department->id,
            'report_date' => now()->subDay()->toDateString(),
        ]);

        $submitted = $this->service()->submit($report, $leader, 'Late one.');

        $this->assertTrue($submitted->is_late);
    }

    public function test_an_unrelated_team_leader_cannot_submit_another_departments_report(): void
    {
        $department = $this->makeDepartment('Marketing');
        $otherDepartment = $this->makeDepartment('Design');
        $otherLeader = $this->makeTeamLeader($otherDepartment);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $this->expectException(AuthorizationException::class);
        $this->service()->submit($report, $otherLeader, 'Not mine.');
    }

    public function test_a_report_cannot_be_submitted_twice(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id]);

        $this->expectException(DepartmentReportException::class);
        $this->service()->submit($report, $leader, 'Second attempt.');
    }

    /** Editing corrects the text only — submitted_at/is_late stay put as historical
     *  facts about the original submission, not about this later correction. */
    public function test_the_owning_departments_effective_leader_can_edit_a_submitted_report(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->submitted($leader, late: true)->create([
            'department_id' => $department->id,
            'details' => 'Original text.',
        ]);
        $originalSubmittedAt = $report->submitted_at;

        $updated = $this->service()->update($report, $leader, 'Corrected text.');

        $this->assertSame('Corrected text.', $updated->details);
        $this->assertTrue($updated->submitted_at->equalTo($originalSubmittedAt));
        $this->assertTrue($updated->is_late);
    }

    public function test_editing_before_submission_fails(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $this->expectException(DepartmentReportException::class);
        $this->service()->update($report, $leader, 'Too early.');
    }

    public function test_an_unrelated_team_leader_cannot_edit_another_departments_report(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $otherDepartment = $this->makeDepartment('Design');
        $otherLeader = $this->makeTeamLeader($otherDepartment);
        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id]);

        $this->expectException(AuthorizationException::class);
        $this->service()->update($report, $otherLeader, 'Not mine.');
    }

    public function test_manager_sees_only_submitted_reports(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);
        $submitted = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id]);
        $unsubmitted = DepartmentDailyReport::factory()->create(['department_id' => $department->id, 'report_date' => now()->addDay()]);

        $visible = DepartmentDailyReport::query()->visibleTo($manager)->pluck('id');

        $this->assertTrue($visible->contains($submitted->id));
        $this->assertFalse($visible->contains($unsubmitted->id));
    }

    public function test_the_owning_tl_sees_their_own_report_even_before_submission(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $visible = DepartmentDailyReport::query()->visibleTo($leader)->pluck('id');

        $this->assertTrue($visible->contains($report->id));
    }

    public function test_moderator_tl_sees_the_content_handoff_only_after_submission(): void
    {
        $content = $this->makeDepartment('Content');
        $content->update(['special_role' => DepartmentSpecialRole::Content]);
        $moderatorDept = $this->makeDepartment('Moderator');
        $moderatorDept->update(['special_role' => DepartmentSpecialRole::Moderator]);
        $moderatorLeader = $this->makeTeamLeader($moderatorDept);
        $contentLeader = $this->makeTeamLeader($content);

        $handoff = DepartmentDailyReport::factory()->moderatorHandoff()->create(['department_id' => $content->id]);

        $this->assertFalse(DepartmentDailyReport::query()->visibleTo($moderatorLeader)->whereKey($handoff->id)->exists());

        $handoff = $this->service()->submit($handoff, $contentLeader, 'Post these three graphics today.');

        $this->assertTrue(DepartmentDailyReport::query()->visibleTo($moderatorLeader)->whereKey($handoff->id)->exists());
    }

    public function test_content_tl_can_submit_both_of_their_own_reports(): void
    {
        $content = $this->makeDepartment('Content');
        $content->update(['special_role' => DepartmentSpecialRole::Content]);
        $contentLeader = $this->makeTeamLeader($content);

        $summary = DepartmentDailyReport::factory()->create(['department_id' => $content->id]);
        $handoff = DepartmentDailyReport::factory()->moderatorHandoff()->create(['department_id' => $content->id]);

        $this->service()->submit($summary, $contentLeader, 'Daily summary.');
        $this->service()->submit($handoff, $contentLeader, 'Publishing list.');

        $this->assertTrue($summary->refresh()->isSubmitted());
        $this->assertTrue($handoff->refresh()->isSubmitted());
    }

    // ------------------------------------------------------- Manager approval (2026-09)

    public function test_a_manager_approves_a_submitted_report(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id]);

        $approved = $this->service()->approve($report, $manager);

        $this->assertTrue($approved->isApproved());
        $this->assertSame($manager->id, $approved->approved_by);
    }

    public function test_a_report_cannot_be_approved_before_it_is_submitted(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $report = DepartmentDailyReport::factory()->create(['department_id' => $department->id]);

        $this->expectException(DepartmentReportException::class);
        $this->service()->approve($report, $manager);
    }

    public function test_a_report_cannot_be_approved_twice(): void
    {
        $department = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id]);
        $this->service()->approve($report, $manager);

        $this->expectException(DepartmentReportException::class);
        $this->service()->approve($report->fresh(), $manager);
    }

    public function test_a_team_leader_cannot_approve_a_report(): void
    {
        $department = $this->makeDepartment('Marketing');
        $leader = $this->makeTeamLeader($department);
        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $department->id]);

        $this->expectException(AuthorizationException::class);
        $this->service()->approve($report, $leader);
    }

    /** The core of the 2026-09 change: approval is what makes another department's
     *  report reach the Moderator, on top of the Content handoff they already see. */
    public function test_moderator_tl_sees_another_departments_report_once_a_manager_approves_it(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($marketing);
        $moderatorDept = $this->makeDepartment('Moderator');
        $moderatorDept->update(['special_role' => DepartmentSpecialRole::Moderator]);
        $moderatorLeader = $this->makeTeamLeader($moderatorDept);

        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $marketing->id]);

        $this->assertFalse(DepartmentDailyReport::query()->visibleTo($moderatorLeader)->whereKey($report->id)->exists());

        $this->service()->approve($report, $manager);

        $this->assertTrue(DepartmentDailyReport::query()->visibleTo($moderatorLeader)->whereKey($report->id)->exists());
    }

    /** A non-Moderator TL never gains cross-department visibility, approved or not. */
    public function test_an_ordinary_tl_never_sees_another_departments_approved_report(): void
    {
        $marketing = $this->makeDepartment('Marketing');
        $manager = $this->makeManager();
        $leader = $this->makeTeamLeader($marketing);
        $otherDepartment = $this->makeDepartment('Design');
        $otherLeader = $this->makeTeamLeader($otherDepartment);

        $report = DepartmentDailyReport::factory()->submitted($leader)->create(['department_id' => $marketing->id]);
        $this->service()->approve($report, $manager);

        $this->assertFalse(DepartmentDailyReport::query()->visibleTo($otherLeader)->whereKey($report->id)->exists());
    }
}
