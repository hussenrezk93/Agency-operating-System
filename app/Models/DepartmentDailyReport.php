<?php

namespace App\Models;

use App\Enums\DepartmentReportType;
use App\Enums\DepartmentSpecialRole;
use App\Enums\RoleCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class DepartmentDailyReport extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'type' => DepartmentReportType::class,
        'report_date' => 'date',
        'auto_summary' => 'array',
        'task_comments' => 'array',
        'submitted_at' => 'datetime',
        'is_late' => 'boolean',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** Product decision 2026-09 — the members' own parts, for a department that writes
     *  its report collectively (Sales). Empty everywhere else. */
    public function contributions(): HasMany
    {
        return $this->hasMany(DepartmentReportContribution::class, 'report_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Distinct tasks that left this department today — TaskWorkflowService writes a
     * 'transferred' row (sendToNextDepartment) or 'redirected' row (redirect) on the
     * OLD step's department at the same moment it writes 'sent_to_department' on the
     * new one, so buildAutoSummary() already captures this per department without any
     * extra query; these two methods just read it back out of the stored snapshot.
     */
    public function sentTasksCount(): int
    {
        return $this->countTasksWithEvent(['transferred', 'redirected']);
    }

    /** Distinct tasks that arrived at this department today, including a brand new task
     *  routed here on creation — both write 'sent_to_department' on the receiving side. */
    public function receivedTasksCount(): int
    {
        return $this->countTasksWithEvent(['sent_to_department']);
    }

    /** @param array<int, string> $eventTypes */
    private function countTasksWithEvent(array $eventTypes): int
    {
        return collect($this->auto_summary ?? [])
            ->filter(fn (array $row) => array_intersect($eventTypes, $row['event_types'] ?? []) !== [])
            ->pluck('task_id')
            ->unique()
            ->count();
    }

    /**
     * One row per distinct task_id in today's auto_summary (a task can appear more
     * than once — e.g. both sent and received in the same day) — the set the TL's
     * per-task comment form iterates over, so a task with several activity rows still
     * gets exactly one comment box instead of one per row.
     */
    public function distinctSummaryTasks(): Collection
    {
        return collect($this->auto_summary ?? [])
            ->filter(fn (array $row) => $row['task_id'] !== null)
            ->unique('task_id')
            ->values();
    }

    /** task_comments is a JSON object keyed by task_id — PHP's own numeric-string key
     *  coercion means an int $taskId already matches the key json_decode produced. */
    public function commentForTask(int $taskId): ?string
    {
        return $this->task_comments[$taskId] ?? null;
    }

    /**
     * The state-dependent half of visibility (DepartmentReportPolicy::view() stays pure
     * WHO) — same split Task::approvedOutputsBefore() already uses for Q26. A Manager
     * never sees a draft; the owning department's TL always sees their own, submitted or
     * not. The Moderator's TL sees three things on top of their own: Content's handoff
     * once submitted (unchanged), PLUS any OTHER department's report once a Manager has
     * approved it (product decision 2026-09 — approval is per-report, granted by
     * DepartmentReportService::approve(), and is what makes a report visible beyond its
     * own department for the first time).
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if ($actor->hasRole(RoleCode::Manager)) {
            return $query->whereNotNull('submitted_at');
        }

        return $query->where(function (Builder $q) use ($actor): void {
            $q->where('department_id', $actor->department_id);

            if ($actor->department?->special_role === DepartmentSpecialRole::Moderator) {
                $q->orWhere(function (Builder $moderator) {
                    $moderator->where('type', DepartmentReportType::ModeratorHandoff->value)
                        ->whereNotNull('submitted_at');
                })->orWhereNotNull('approved_at');
            }
        });
    }
}
