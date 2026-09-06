<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateOwnEmailRequest;
use App\Models\Task;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BRD §18.1 — every role edits their own personal email here (not through
 * UserController, which is Admin/Manager acting on someone ELSE's account).
 * The self-edit still goes through UserService::updateProfile()'s same
 * pending-email + verification flow, so it is audited identically either way.
 *
 * The optional {user} parameter (mirroring PerformanceController's {user?}) opens the
 * Activity tab up to viewing someone else's task history — self is always allowed, and
 * anyone else is gated by UserPolicy::viewPerformance() (same boundary already used for
 * the performance page: self, Manager sees everyone, TL sees only their own department;
 * Admin is excluded, same as every other task-content surface). The Account tab (email
 * + avatar) never renders for anyone but self — its forms always act on $request->user(),
 * never on the viewed subject.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    public function edit(Request $request, ?User $user = null): View
    {
        $actor = $request->user();
        $subject = $user ?? $actor;
        $isSelf = $subject->is($actor);

        if (! $isSelf) {
            $this->authorize('viewPerformance', $subject);
        }

        $date = $request->query('date');
        $date = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : null;

        $tasks = Task::query()
            ->where(function (Builder $query) use ($subject): void {
                $query->where('created_by', $subject->id)
                    ->orWhereHas('steps.assignments', fn (Builder $q) => $q->where('assignee_id', $subject->id));
            })
            // "Activity on :date" means the subject actually did something to the task
            // that day — not merely that the task exists — same task_status_history
            // convention DepartmentReportService::buildAutoSummary() already uses.
            ->when($date, fn (Builder $query) => $query->whereHas(
                'history',
                fn (Builder $q) => $q->where('changed_by', $subject->id)->whereDate('created_at', $date),
            ))
            ->with(['currentStep.department:id,name', 'project:id,name'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('profile.edit', [
            'user' => $subject,
            'isSelf' => $isSelf,
            'tasks' => $tasks,
            'filterDate' => $date,
        ]);
    }

    public function update(UpdateOwnEmailRequest $request): JsonResponse|RedirectResponse
    {
        $actor = $request->user();

        $updated = $this->service->updateProfile(
            $actor,
            ['personal_email' => $request->string('personal_email')->toString()],
            $actor,
        );

        if (! $request->expectsJson()) {
            return redirect()->route('profile.edit')->with('status', __('agencyos.profile.flash.email_updated'));
        }

        return response()->json(['data' => $updated]);
    }

    public function updateAvatar(UpdateAvatarRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');

        $this->service->updateAvatar($actor, $path, $actor);

        return redirect()->route('profile.edit')->with('status', __('agencyos.profile.flash.avatar_updated'));
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $this->service->removeAvatar($actor, $actor);

        return redirect()->route('profile.edit')->with('status', __('agencyos.profile.flash.avatar_removed'));
    }
}
