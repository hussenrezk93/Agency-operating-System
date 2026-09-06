<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * BRD §11.1 — ONE logical notification event. The per-channel outcome lives in
 * `notification_deliveries`, which is why a bounced email can never erase the fact that the
 * user was notified: the event row is untouched by delivery problems.
 *
 * `entity_type` / `entity_id` are a loose pointer rather than a foreign key, because a
 * notification may refer to a task, a step, a project or nothing at all.
 */
class Notification extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * `is_read` and `read_at` are kept in step by a CHECK constraint, so they are always
     * written together rather than separately.
     */
    public function markRead(): void
    {
        if ($this->is_read) {
            return;
        }

        $this->forceFill(['is_read' => true, 'read_at' => now()])->save();
    }

    public function delivery(string $channel): ?NotificationDelivery
    {
        return $this->deliveries->firstWhere('channel', $channel);
    }

    /**
     * Where clicking this notification should take the user. entity_type is a loose
     * pointer (see class doc comment); 'task'/'project' resolve to a URL from entity_id
     * alone, no query needed. 'department_report' needs its report_date, which entity_id
     * alone doesn't carry — pass $reportDates (a report id => report_date map) when
     * resolving many notifications at once (NotificationController::index(), up to 50 a
     * page) to avoid a query per row; omit it for a single notification
     * (NotificationController::markRead()), where one cheap query is fine.
     */
    public function targetUrl(?Collection $reportDates = null): ?string
    {
        if ($this->entity_id === null) {
            return null;
        }

        return match ($this->entity_type) {
            'task' => route('tasks.show', $this->entity_id),
            'project' => route('projects.show', $this->entity_id),
            'department_report' => $this->departmentReportTargetUrl($reportDates),
            default => null,
        };
    }

    private function departmentReportTargetUrl(?Collection $reportDates): ?string
    {
        $date = $reportDates?->get($this->entity_id)
            ?? DepartmentDailyReport::whereKey($this->entity_id)->value('report_date');

        // report_date is cast to a Carbon date on the model, so both the map lookup and
        // the value() fallback return a Carbon instance, not a raw string — route()
        // would otherwise stringify it as "Y-m-d H:i:s" and fail the route's
        // \d{4}-\d{2}-\d{2} constraint (404).
        if ($date instanceof Carbon) {
            $date = $date->toDateString();
        }

        return $date !== null ? route('department-reports.show', $date) : null;
    }
}
