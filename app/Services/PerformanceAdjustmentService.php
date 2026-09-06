<?php

namespace App\Services;

use App\Enums\AdjustmentType;
use App\Models\PerformanceAdjustment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * The only writer of performance_adjustments (product decision 2026-09). Kept out of
 * PerformanceService on purpose: that class is the single place deadline counts are
 * CALCULATED, and money is a separate, human decision that must not blend into it.
 */
class PerformanceAdjustmentService
{
    public function __construct(private readonly AuditService $audit) {}

    public function add(
        User $subject,
        User $actor,
        AdjustmentType $type,
        string $amount,
        string $reason,
        Carbon $monthStart,
    ): PerformanceAdjustment {
        Gate::forUser($actor)->authorize('create', PerformanceAdjustment::class);

        $adjustment = PerformanceAdjustment::create([
            'user_id' => $subject->id,
            'month_start' => $monthStart->clone()->startOfMonth()->toDateString(),
            'type' => $type->value,
            'amount' => $amount,
            'reason' => trim($reason),
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);

        $this->audit->log(
            action: 'performance_adjustment.added',
            entityType: 'performance_adjustment',
            entityId: $adjustment->id,
            after: [
                'user_id' => $subject->id,
                'month_start' => $adjustment->month_start->toDateString(),
                'type' => $type->value,
                'amount' => $amount,
            ],
            actorId: $actor->id,
        );

        return $adjustment;
    }

    public function remove(PerformanceAdjustment $adjustment, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $adjustment);

        $before = [
            'user_id' => $adjustment->user_id,
            'month_start' => $adjustment->month_start->toDateString(),
            'type' => $adjustment->type->value,
            'amount' => (string) $adjustment->amount,
            'reason' => $adjustment->reason,
        ];

        $adjustment->delete();

        $this->audit->log(
            action: 'performance_adjustment.removed',
            entityType: 'performance_adjustment',
            entityId: $adjustment->id,
            before: $before,
            actorId: $actor->id,
        );
    }
}
