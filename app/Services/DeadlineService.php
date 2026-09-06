<?php

namespace App\Services;

use App\Enums\DeadlineStatus;
use App\Enums\WorkflowStatus;
use App\Events\TaskStepDueSoon;
use App\Events\TaskStepOverdue;
use App\Models\TaskStep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Phase 0 Technical Plan §7 — the ONLY writer of the time-driven `deadline_status`
 * moves (`on_time` → `due_soon` → `overdue`). `TaskWorkflowService` keeps writing the
 * non-time-driven values it always has (`not_started`, `closed`, and now `paused`) —
 * this class never touches those, and never touches a step whose task is on hold or
 * whose workflow_status is already terminal.
 *
 * Trigger table (Phase 0 Technical Plan):
 *   on_time  — now < due − 24h
 *   due_soon — due − 24h <= now <= due
 *   overdue  — now > due
 *
 * Product decision 2026-09 — once a step is submitted (UnderReview), the assignee has
 * done their part; the deadline clock is theirs, not the reviewer's, so
 * sweepLiveSteps() stops reclassifying a step the moment it leaves InProgress for
 * review. Whatever deadline_status it already carried at that instant (on_time,
 * due_soon, or overdue — an honest reflection of whether THEY were late) simply
 * freezes until the review decision moves it on (RequestChanges reopens it to
 * InProgress-equivalent ChangesRequested, which sweeps again; Approve/Redirect/Cancel
 * close it outright via TaskWorkflowService::closeStep()).
 */
class DeadlineService
{
    public function classify(Carbon $dueAt, Carbon $now): DeadlineStatus
    {
        if ($now->lt($dueAt->clone()->subHours(24))) {
            return DeadlineStatus::OnTime;
        }

        if ($now->lte($dueAt)) {
            return DeadlineStatus::DueSoon;
        }

        return DeadlineStatus::Overdue;
    }

    /** @return bool true when this call changed the stored status */
    public function recomputeStep(TaskStep $step, ?Carbon $now = null): bool
    {
        if ($step->workflow_status->isTerminal() || $step->current_due_at === null) {
            return false;
        }

        if ($step->task->isOnHold()) {
            return false;
        }

        $status = $this->classify($step->current_due_at, $now ?? now());

        if ($step->deadline_status === $status) {
            return false;
        }

        $step->forceFill(['deadline_status' => $status->value])->save();

        // Only the two reminder-worthy transitions dispatch — never on_time/closed/paused,
        // and never twice for the same step (recomputeStep only writes when the value
        // actually changes, which is the idempotency guard the Phase 0 plan calls for).
        if ($status === DeadlineStatus::DueSoon) {
            TaskStepDueSoon::dispatch($step);
        } elseif ($status === DeadlineStatus::Overdue) {
            TaskStepOverdue::dispatch($step);
        }

        return true;
    }

    /**
     * Every live, due-dated step, reclassified. Both `agencyos:deadlines-due-soon` and
     * `agencyos:deadlines-overdue` call this same sweep — it is idempotent (a step whose
     * classification hasn't changed is never written), so running it from two commands
     * costs nothing beyond the read.
     *
     * @return Collection<int, TaskStep> the steps whose deadline_status changed this sweep
     */
    public function sweepLiveSteps(?Carbon $now = null): Collection
    {
        $now ??= now();
        $changed = collect();

        TaskStep::query()
            ->whereNotNull('current_due_at')
            ->whereIn('workflow_status', [
                WorkflowStatus::InProgress->value,
                WorkflowStatus::ChangesRequested->value,
            ])
            ->with('task')
            ->chunkById(200, function (Collection $steps) use ($now, $changed): void {
                foreach ($steps as $step) {
                    if ($this->recomputeStep($step, $now)) {
                        $changed->push($step);
                    }
                }
            });

        return $changed;
    }
}
