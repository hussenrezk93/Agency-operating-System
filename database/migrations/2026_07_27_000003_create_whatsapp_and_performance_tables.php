<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2 (completing the ERD) — the WhatsApp invite-link lifecycle and monthly performance.
 *
 * Schema only. Invite distribution is Phase 4 and the score calculation is Phase 9.
 *
 * BRD §7.3 / §22.15 — THE LINK IS VERSIONED, AND THAT IS THE WHOLE MECHANISM.
 * Agency OS never calls the WhatsApp Business API: it stores an invite link a human created
 * and distributes it. Every edit creates a NEW version row and re-invites the current
 * members exactly once for that version. `project_invite_deliveries` is unique on
 * (project, user, link_version, channel), so "invite each member once per version, never
 * twice" (BRD §22.13) is a database guarantee rather than an application convention — a
 * retry or a double-click cannot produce a second invite.
 *
 * `pwlv_one_current_version` — MySQL has no partial/filtered index (unlike the original
 * PostgreSQL design), so "one current version per project" is expressed as a generated
 * column that is NULL unless `is_current` is true, with a plain UNIQUE index on that
 * column — MySQL/InnoDB treats every NULL as distinct, so non-current rows never collide.
 * Same technique below for `mps_department_month_type`. `projects.whatsapp_link_version`
 * already exists on the projects table; this table holds the history behind it.
 *
 * BRD §17 — PERFORMANCE IS A STORED SNAPSHOT, NOT A LIVE QUERY.
 * The monthly score depends on the state of deadlines at month end. Recomputing it later
 * from current data would silently rewrite history the moment a hold or a reassignment
 * landed, so it is calculated once and frozen. `score` is nullable on purpose: BRD §17 says
 * a user with no steps due in a month shows N/A, and NULL is that N/A — storing 0 would put
 * an unearned zero into the department average.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_whatsapp_link_versions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->integer('version_no');
            $t->text('group_url')->nullable();      // null when the action is 'removed'
            $t->string('group_label')->nullable();
            $t->string('action_type', 16);          // created | updated | removed
            $t->foreignId('created_by')->constrained('users');
            $t->timestampTz('created_at')->useCurrent();
            $t->boolean('is_current')->default(true);

            $t->unique(['project_id', 'version_no'], 'pwlv_project_version_unique');

            // NULL unless this is the current version — collapses to "one current
            // version per project."
            $t->unsignedBigInteger('current_version_project_id')->nullable()
                ->virtualAs('CASE WHEN is_current THEN project_id END');
            $t->unique('current_version_project_id', 'pwlv_one_current_version');
        });

        Schema::create('project_invite_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Which department qualified this person for membership (BRD §7.3). Null when
            // they qualified as creator or manager rather than through a department.
            $t->foreignId('department_id')->nullable()->constrained('departments');
            $t->foreignId('link_version_id')->constrained('project_whatsapp_link_versions');
            $t->foreignId('notification_id')->nullable()->constrained('notifications');
            // department | creator | manager | temp_tl
            $t->string('recipient_source', 16);
            $t->string('channel', 16);              // in_app | email
            $t->string('status', 16)->default('queued'); // queued | sent | failed
            $t->timestampTz('sent_at')->nullable();
            $t->text('error')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            // BRD §22.13 — one invite per member per version per channel. Enforced here.
            $t->unique(
                ['project_id', 'user_id', 'link_version_id', 'channel'],
                'pid_project_user_version_channel_unique',
            );
            $t->index(['project_id', 'status']);
        });

        Schema::create('monthly_performance_snapshots', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained('users');
            $t->foreignId('department_id')->nullable()->constrained('departments');
            $t->date('month_start');
            $t->integer('due_steps')->default(0);
            $t->integer('on_time_steps')->default(0);
            $t->integer('overdue_steps')->default(0);
            // NULL = N/A (no steps due that month) — deliberately not 0. See the class note.
            $t->decimal('score', 5, 2)->nullable();
            // employee | tl_personal | tl_team | department
            $t->string('snapshot_type', 16);
            $t->timestampTz('calculated_at')->useCurrent();

            $t->index(['month_start', 'snapshot_type']);

            // "One snapshot per USER per month per type" — the original condition
            // (`WHERE user_id IS NOT NULL`) is exactly "this indexed column is not
            // null," which a plain composite UNIQUE index already gives for free on
            // MySQL/InnoDB (every NULL user_id row is treated as distinct) — no
            // generated column needed here, unlike the department case just below.
            $t->unique(['user_id', 'month_start', 'snapshot_type'], 'mps_user_month_type');

            // "One snapshot per DEPARTMENT per month per type" — this condition also
            // requires user_id IS NULL, not just department_id IS NOT NULL, so (unlike
            // the user case above) it needs an explicit generated column rather than
            // relying on MySQL's NULL-distinctness shortcut.
            $t->string('dept_snapshot_key', 80)->nullable()->virtualAs(
                "CASE WHEN department_id IS NOT NULL AND user_id IS NULL
                    THEN CONCAT(department_id, '|', month_start, '|', snapshot_type) END"
            );
            $t->unique('dept_snapshot_key', 'mps_department_month_type');
        });

        DB::statement("ALTER TABLE project_whatsapp_link_versions ADD CONSTRAINT pwlv_action_check
            CHECK (action_type IN ('created', 'updated', 'removed'))");

        // A removal clears the link; anything else must carry one.
        DB::statement("ALTER TABLE project_whatsapp_link_versions ADD CONSTRAINT pwlv_url_check
            CHECK ((action_type = 'removed') = (group_url IS NULL))");

        DB::statement('ALTER TABLE project_whatsapp_link_versions ADD CONSTRAINT pwlv_version_check
            CHECK (version_no > 0)');

        DB::statement("ALTER TABLE project_invite_deliveries ADD CONSTRAINT pid_source_check
            CHECK (recipient_source IN ('department', 'creator', 'manager', 'temp_tl'))");
        DB::statement("ALTER TABLE project_invite_deliveries ADD CONSTRAINT pid_channel_check
            CHECK (channel IN ('in_app', 'email'))");
        DB::statement("ALTER TABLE project_invite_deliveries ADD CONSTRAINT pid_status_check
            CHECK (status IN ('queued', 'sent', 'failed'))");
        DB::statement("ALTER TABLE project_invite_deliveries ADD CONSTRAINT pid_sent_at_check
            CHECK (status <> 'sent' OR sent_at IS NOT NULL)");

        DB::statement("ALTER TABLE monthly_performance_snapshots ADD CONSTRAINT mps_type_check
            CHECK (snapshot_type IN ('employee', 'tl_personal', 'tl_team', 'department'))");

        // ERD: the subject required by the snapshot type. A department snapshot names a
        // department; every per-person snapshot names a user.
        DB::statement("ALTER TABLE monthly_performance_snapshots ADD CONSTRAINT mps_subject_check
            CHECK (
                (snapshot_type = 'department' AND department_id IS NOT NULL AND user_id IS NULL)
                OR
                (snapshot_type <> 'department' AND user_id IS NOT NULL)
            )");

        // BRD §17 — the score is a percentage, and the counts must add up.
        DB::statement('ALTER TABLE monthly_performance_snapshots ADD CONSTRAINT mps_score_range_check
            CHECK (score IS NULL OR (score >= 0 AND score <= 100))');
        DB::statement('ALTER TABLE monthly_performance_snapshots ADD CONSTRAINT mps_counts_check
            CHECK (due_steps >= 0 AND on_time_steps >= 0 AND overdue_steps >= 0
                   AND on_time_steps + overdue_steps <= due_steps)');

        // N/A exactly when nothing was due — the rule that keeps 0 and N/A distinct.
        DB::statement('ALTER TABLE monthly_performance_snapshots ADD CONSTRAINT mps_na_check
            CHECK ((due_steps = 0) = (score IS NULL))');

        // The month is a real month boundary, so two snapshots cannot disagree on it.
        // MySQL-portable rewrite of the original `date_trunc('month', month_start)::date`
        // (Postgres-only) — month_start is always the first of its month, so this checks
        // the same thing.
        DB::statement('ALTER TABLE monthly_performance_snapshots ADD CONSTRAINT mps_month_start_check
            CHECK (DAY(month_start) = 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_performance_snapshots');
        Schema::dropIfExists('project_invite_deliveries');
        Schema::dropIfExists('project_whatsapp_link_versions');
    }
};
