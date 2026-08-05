<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\InviteRecipientSource;
use App\Enums\NotificationChannel;
use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Enums\UserStatus;
use App\Enums\WhatsappLinkAction;
use App\Jobs\SendProjectInviteEmailJob;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectInviteDelivery;
use App\Models\ProjectWhatsappLinkVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BRD §7.3 — the WhatsApp invite-link lifecycle. Agency OS never touches the WhatsApp
 * Business API: it stores an invite link a human created outside the system and
 * distributes it. Every edit creates a NEW version and re-invites the current
 * members exactly once for that version (the unique index on
 * project_invite_deliveries makes "never twice" a database guarantee).
 *
 * PHASE 7 — fan-out now activates the ledger it writes: a newly-created row gets a
 * linked `Notification` (via `NotificationService::createRecord()`, which deliberately
 * skips `notification_deliveries` — this ledger IS the delivery record for an invite),
 * the InApp row is marked sent immediately, and the Email row dispatches
 * `SendProjectInviteEmailJob` when the recipient can receive email.
 */
class ProjectWhatsappService
{
    private const URL_PATTERN = '#^https://chat\.whatsapp\.com/.+$#i';

    public function __construct(
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function setLink(Project $project, string $url, ?string $label, User $actor): ProjectWhatsappLinkVersion
    {
        Gate::forUser($actor)->authorize('update', $project);
        $this->assertActive($project);
        $this->assertValidUrl($url);

        return DB::transaction(function () use ($project, $url, $label, $actor): ProjectWhatsappLinkVersion {
            $current = $project->currentWhatsappLinkVersion;
            $actionType = $current === null ? WhatsappLinkAction::Created : WhatsappLinkAction::Updated;
            $nextVersionNo = ((int) $project->whatsappLinkVersions()->max('version_no')) + 1;

            $current?->forceFill(['is_current' => false])->save();

            $version = $project->whatsappLinkVersions()->create([
                'version_no' => $nextVersionNo,
                'group_url' => $url,
                'group_label' => $label,
                'action_type' => $actionType->value,
                'created_by' => $actor->id,
                'created_at' => now(),
                'is_current' => true,
            ]);

            $project->forceFill([
                'whatsapp_group_url' => $url,
                'whatsapp_group_label' => $label,
                'whatsapp_link_version' => $nextVersionNo,
                'whatsapp_link_updated_at' => now(),
                'whatsapp_link_updated_by' => $actor->id,
            ])->save();

            $this->audit->log(
                action: 'project.whatsapp_link_'.$actionType->value,
                entityType: 'project',
                entityId: $project->id,
                after: ['version_no' => $nextVersionNo],
                actorId: $actor->id,
            );

            $this->fanOutForVersion($project, $version, $this->resolveMembers($project));

            return $version;
        });
    }

    public function removeLink(Project $project, User $actor): ProjectWhatsappLinkVersion
    {
        Gate::forUser($actor)->authorize('update', $project);
        $this->assertActive($project);

        return DB::transaction(function () use ($project, $actor): ProjectWhatsappLinkVersion {
            $nextVersionNo = ((int) $project->whatsappLinkVersions()->max('version_no')) + 1;

            $project->currentWhatsappLinkVersion?->forceFill(['is_current' => false])->save();

            $version = $project->whatsappLinkVersions()->create([
                'version_no' => $nextVersionNo,
                'group_url' => null,
                'group_label' => null,
                'action_type' => WhatsappLinkAction::Removed->value,
                'created_by' => $actor->id,
                'created_at' => now(),
                'is_current' => true,
            ]);

            $project->forceFill([
                'whatsapp_group_url' => null,
                'whatsapp_group_label' => null,
                'whatsapp_link_version' => $nextVersionNo,
                'whatsapp_link_updated_at' => now(),
                'whatsapp_link_updated_by' => $actor->id,
            ])->save();

            $this->audit->log(
                action: 'project.whatsapp_link_removed',
                entityType: 'project',
                entityId: $project->id,
                after: ['version_no' => $nextVersionNo],
                actorId: $actor->id,
            );

            return $version;
        });
    }

    /** BRD §7.3 — invite only the new department's members, for the current version. */
    public function fanOutForDepartment(Project $project, Department $department): void
    {
        $version = $project->currentWhatsappLinkVersion;

        if ($version === null || ! $project->canDistributeWhatsappInvites()) {
            return;
        }

        $this->fanOutForVersion($project, $version, $this->membersForDepartments(collect([$department])));
    }

    /**
     * BRD §7.3 — a user newly belonging to $department (a fresh account, or moving in
     * from elsewhere) is invited to every active project that department participates
     * in, exactly like a new department being added — just scoped to this one person.
     */
    public function fanOutForUserJoiningDepartment(User $user, Department $department): void
    {
        if ($user->status !== UserStatus::Active) {
            return;
        }

        foreach ($department->projects()->wherePivot('is_active', true)->get() as $project) {
            $version = $project->currentWhatsappLinkVersion;

            if ($version === null || ! $project->canDistributeWhatsappInvites()) {
                continue;
            }

            $this->fanOutForVersion($project, $version, collect([[
                'user' => $user, 'source' => InviteRecipientSource::Department, 'department_id' => $department->id,
            ]]));
        }
    }

    /**
     * BRD §7.3 — Agency OS never touches the WhatsApp Business API, so it cannot remove
     * anyone from a group itself. When a user stops belonging to $department (moved,
     * disabled) or $department stops participating in a project, this alerts the
     * department's current effective Team Leader to do it by hand. In-app only, like
     * every other "please go do this manually" alert in the app.
     */
    public function alertLeaderToRemoveMember(User $formerMember, Department $department): void
    {
        $leader = $department->effectiveLeader();

        if ($leader === null || $leader->is($formerMember)) {
            return;
        }

        $this->notifications->notify(
            $leader,
            'whatsapp.member_left',
            __('agencyos.notifications.messages.whatsapp_member_left_title'),
            __('agencyos.notifications.messages.whatsapp_member_left_body', [
                'user' => $formerMember->full_name,
                'department' => $department->name,
            ]),
            'department',
            $department->id,
            allowEmail: false,
        );
    }

    /** BRD §7.3 — a department was removed from one specific project's participation. */
    public function alertLeaderDepartmentRemoved(Project $project, Department $department): void
    {
        $leader = $department->effectiveLeader();

        if ($leader === null) {
            return;
        }

        $this->notifications->notify(
            $leader,
            'whatsapp.department_removed',
            __('agencyos.notifications.messages.whatsapp_department_removed_title'),
            __('agencyos.notifications.messages.whatsapp_department_removed_body', [
                'department' => $department->name,
                'project' => $project->name,
            ]),
            'project',
            $project->id,
            allowEmail: false,
        );
    }

    /**
     * BRD §7.3 — project membership: every active user of an active participating
     * department, the primary/temporary TL of those departments, the project
     * creator, and every active Manager.
     *
     * @return Collection<int, array{user: User, source: InviteRecipientSource, department_id: ?int}>
     */
    public function resolveMembers(Project $project): Collection
    {
        $members = $this->membersForDepartments($project->departments()->wherePivot('is_active', true)->get());

        $creator = $project->creator;
        if ($creator !== null && ! $members->has($creator->id)) {
            $members->put($creator->id, [
                'user' => $creator, 'source' => InviteRecipientSource::Creator, 'department_id' => null,
            ]);
        }

        User::query()
            ->whereHas('role', fn ($q) => $q->where('code', RoleCode::Manager->value))
            ->where('status', UserStatus::Active->value)
            ->get()
            ->each(function (User $manager) use ($members): void {
                if (! $members->has($manager->id)) {
                    $members->put($manager->id, [
                        'user' => $manager, 'source' => InviteRecipientSource::Manager, 'department_id' => null,
                    ]);
                }
            });

        return $members->values();
    }

    /**
     * PERFORMANCE: re-fetches the given departments with `users` (active only) and
     * `leadershipAssignments.user` eager-loaded, regardless of what the caller passed
     * in — turns what used to be 2 extra queries PER department into a fixed 3 queries
     * total. Same filter (`status = active`), same department set (by id), so the
     * resulting membership map is identical either way.
     *
     * @return Collection<int, array{user: User, source: InviteRecipientSource, department_id: ?int}>
     */
    private function membersForDepartments(iterable $departments): Collection
    {
        $departments = Department::query()
            ->whereIn('id', collect($departments)->pluck('id'))
            ->with([
                'users' => fn ($q) => $q->where('status', UserStatus::Active->value),
                'leadershipAssignments.user',
            ])
            ->get();

        $members = collect();

        foreach ($departments as $department) {
            foreach ($department->users as $user) {
                $members->put($user->id, [
                    'user' => $user, 'source' => InviteRecipientSource::Department, 'department_id' => $department->id,
                ]);
            }

            // A temporary TL from ANOTHER department is not in the loop above.
            $tempLeader = $department->temporaryLeader();
            if ($tempLeader !== null && ! $members->has($tempLeader->id)) {
                $members->put($tempLeader->id, [
                    'user' => $tempLeader, 'source' => InviteRecipientSource::TemporaryTl, 'department_id' => $department->id,
                ]);
            }
        }

        return $members;
    }

    /**
     * Writes the invite LEDGER — one `queued` row per member per channel per version.
     * The unique index on (project, user, link_version, channel) makes re-running this
     * idempotent: firstOrCreate never produces a second row for the same version.
     *
     * @param  Collection<int, array{user: User, source: InviteRecipientSource, department_id: ?int}>  $members
     */
    private function fanOutForVersion(Project $project, ProjectWhatsappLinkVersion $version, Collection $members): void
    {
        foreach ($members as $member) {
            $notification = null;

            foreach ([NotificationChannel::InApp, NotificationChannel::Email] as $channel) {
                $delivery = ProjectInviteDelivery::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'user_id' => $member['user']->id,
                        'link_version_id' => $version->id,
                        'channel' => $channel->value,
                    ],
                    [
                        'department_id' => $member['department_id'],
                        'recipient_source' => $member['source']->value,
                        'status' => DeliveryStatus::Queued->value,
                        'created_at' => now(),
                    ],
                );

                if (! $delivery->wasRecentlyCreated) {
                    continue;
                }

                $notification ??= $this->notifications->createRecord(
                    $member['user'],
                    'project.invite',
                    __('agencyos.notifications.messages.project_invite_title'),
                    __('agencyos.notifications.messages.project_invite_body', ['project' => $project->name]),
                    'project',
                    $project->id,
                );

                $delivery->forceFill(['notification_id' => $notification->id])->save();

                if ($channel === NotificationChannel::InApp) {
                    $delivery->markSent();
                } elseif ($member['user']->canReceiveEmail()) {
                    SendProjectInviteEmailJob::dispatch($delivery);
                }
            }
        }
    }

    private function assertActive(Project $project): void
    {
        if ($project->status !== ProjectStatus::Active) {
            throw ValidationException::withMessages([
                'project' => __('The WhatsApp link can only be changed while the project is Active.'),
            ]);
        }
    }

    private function assertValidUrl(string $url): void
    {
        if (! preg_match(self::URL_PATTERN, $url)) {
            throw ValidationException::withMessages([
                'url' => __('The link must be a chat.whatsapp.com group invite URL.'),
            ]);
        }
    }
}
