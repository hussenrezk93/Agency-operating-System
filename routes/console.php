<?php

use App\Services\DepartmentReportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 | APPROVED DECISION Q13 — temporary-TL periods start and end automatically.
 | Runs on Africa/Cairo time (config/app.php); the command is idempotent.
 */
Schedule::command('agencyos:process-leadership-transitions')
    ->dailyAt('00:05')
    ->withoutOverlapping();

/*
 | Phase 0 Technical Plan §7 — deadline_status reclassification (due_soon/overdue).
 | Runs on Africa/Cairo time (config/app.php); DeadlineService::sweepLiveSteps() is
 | idempotent, so both commands running back-to-back is harmless.
 */
Schedule::command('agencyos:deadlines-due-soon')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('agencyos:deadlines-overdue')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
 | Product decision (on top of Q28) — general notification emails are batched into one
 | digest per recipient instead of one email per event, so a busy workflow does not
 | flood an inbox. This sweep also covers that ledger's retries (see the command's own
 | docblock). Runs on Africa/Cairo time.
 */
Schedule::command('agencyos:notification-digest-sweep')
    ->everyThreeHours()
    ->withoutOverlapping();

/*
 | BRD §14 / Phase 0 Technical Plan §7 — close every due 2-hour chat digest window.
 | Runs on Africa/Cairo time.
 */
Schedule::command('agencyos:chat-digests')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

/*
 | Q28 / OPEN-DECISIONS.md §C — retry failed WhatsApp invite emails and failed chat
 | digest batches (neither ledger has an attempt_count/next_attempt_at backoff clock,
 | unlike general notifications), and alert Managers (in-app only) about repeated
 | failures. Runs on Africa/Cairo time.
 */
Schedule::command('agencyos:notification-retry-sweep')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('agencyos:notification-failure-alert')
    ->dailyAt('07:00')
    ->withoutOverlapping();

/*
 | BRD §17 / Phase 0 Technical Plan §7 — monthly performance scoring. The final snapshot
 | is built once the month closes; the daily refresh keeps the in-progress month's
 | provisional numbers reasonably current for dashboards. Runs on Africa/Cairo time.
 */
Schedule::command('agencyos:performance-snapshot')
    ->monthlyOn(1, '00:30')
    ->withoutOverlapping();

Schedule::command('agencyos:performance-refresh')
    ->dailyAt('01:00')
    ->withoutOverlapping();

/*
 | Daily Department Reports (product decision 2026-08) — one auto-filled report per
 | active department, plus a second blank one for whichever department has
 | special_role=content, addressed to special_role=moderator. Runs on Africa/Cairo time;
 | DepartmentReportService::generateForToday() is idempotent (unique index on
 | department_id/report_date/type, firstOrCreate + wasRecentlyCreated skip re-notifying).
 */
Schedule::command('agencyos:generate-daily-department-reports')
    ->dailyAt(DepartmentReportService::GENERATION_TIME)
    ->withoutOverlapping();
