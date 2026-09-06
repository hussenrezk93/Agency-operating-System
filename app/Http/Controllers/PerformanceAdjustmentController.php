<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentType;
use App\Http\Requests\StorePerformanceAdjustmentRequest;
use App\Models\PerformanceAdjustment;
use App\Models\User;
use App\Services\PerformanceAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** The Manager's bonus/deduction entries on the reports page (product decision 2026-09). */
class PerformanceAdjustmentController extends Controller
{
    public function __construct(private readonly PerformanceAdjustmentService $adjustments) {}

    public function store(StorePerformanceAdjustmentRequest $request): RedirectResponse
    {
        $month = Carbon::createFromFormat('Y-m-d', $request->string('month').'-01')->startOfMonth();

        $this->adjustments->add(
            User::findOrFail($request->integer('user_id')),
            $request->user(),
            AdjustmentType::from($request->string('type')->toString()),
            $request->string('amount')->toString(),
            $request->string('reason')->toString(),
            $month,
        );

        return redirect()
            ->route($this->backTo($request), ['month' => $month->format('Y-m')])
            ->with('status', __('agencyos.reports.flash.adjustment_added'));
    }

    public function destroy(Request $request, PerformanceAdjustment $adjustment): RedirectResponse
    {
        $month = $adjustment->month_start->format('Y-m');

        $this->adjustments->remove($adjustment, $request->user());

        return redirect()
            ->route($this->backTo($request), ['month' => $month])
            ->with('status', __('agencyos.reports.flash.adjustment_removed'));
    }

    /** Bonuses and deductions are entered from two screens now — the reports table and
     *  the payroll page — and each has to return to where the click came from. */
    private function backTo(Request $request): string
    {
        return $request->string('return')->toString() === 'payroll' ? 'payroll.index' : 'reports.index';
    }
}
