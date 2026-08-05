<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 1B — control actions and the timeline.
 *
 * APPROVED DECISION Q5/Q9: On Hold PAUSES the deadline clock and extends the due date by
 * the paused duration. `task_holds.paused_seconds` is written on resume so the extension
 * is a stored fact, never recomputed from wall-clock guesses.
 *
 * APPROVED CHANGE REQUEST Q23 (Project On Hold): a project hold is NOT visual-only. It
 * writes a `project_holds` row AND a child `task_holds` row for every unfinished task, so
 * the deadline engine keeps exactly ONE accounting path. Child rows carry
 * `project_hold_id` so resuming the project resumes precisely what it paused — and a task
 * held individually beforehand stays held.
 *
 * BRD §10: Redirect is a Manager-only correction with a mandatory reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects');
            $t->foreignId('created_by')->constrained('users');
            $t->text('reason');                                   // mandatory (Q23)
            $t->timestampTz('started_at')->useCurrent();
            $t->timestampTz('ended_at')->nullable();
            $t->foreignId('resumed_by')->nullable()->constrained('users');
            $t->index(['project_id', 'ended_at']);

            // NULL unless this hold is still open — collapses to "one open hold per
            // project at a time." MySQL has no partial/filtered index equivalent.
            $t->unsignedBigInteger('open_project_id')->nullable()
                ->virtualAs('CASE WHEN ended_at IS NULL THEN project_id END');
            $t->unique('open_project_id', 'project_holds_one_open');
        });

        Schema::create('task_holds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('task_step_id')->nullable()->constrained('task_steps');
            // Set when this hold was caused by a project-level hold (Q23).
            $t->foreignId('project_hold_id')->nullable()->constrained('project_holds');
            $t->foreignId('created_by')->constrained('users');
            $t->text('reason');                                   // mandatory (BRD §10)
            $t->timestampTz('started_at')->useCurrent();
            $t->timestampTz('ended_at')->nullable();
            $t->foreignId('resumed_by')->nullable()->constrained('users');
            $t->unsignedBigInteger('paused_seconds')->nullable(); // written on resume
            $t->index(['task_id', 'ended_at']);

            // NULL unless this hold is still open — collapses to "one open hold per
            // task at a time," same technique as project_holds above.
            $t->unsignedBigInteger('open_task_id')->nullable()
                ->virtualAs('CASE WHEN ended_at IS NULL THEN task_id END');
            $t->unique('open_task_id', 'task_holds_one_open');
        });

        Schema::create('task_redirects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('from_step_id')->constrained('task_steps');
            $t->foreignId('to_step_id')->nullable()->constrained('task_steps');
            $t->foreignId('to_department_id')->constrained('departments');
            $t->foreignId('redirected_by')->constrained('users'); // Manager only (policy)
            $t->text('reason');                                   // mandatory
            $t->timestampTz('created_at')->useCurrent();
            $t->index('task_id');
        });

        Schema::create('task_status_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('task_step_id')->nullable()->constrained('task_steps');
            $t->string('event_type');
            $t->string('from_status')->nullable();
            $t->string('to_status')->nullable();
            $t->foreignId('changed_by')->nullable()->constrained('users'); // null = system
            $t->foreignId('department_id')->nullable()->constrained('departments');
            $t->text('reason')->nullable();
            $t->json('context')->nullable();                     // actor role, dates, counts
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['task_id', 'created_at']);
        });

        // Only one open hold per task/project at a time — prevents double-pausing the
        // clock (enforced above via the generated-column + unique-index pairs).
        DB::statement('ALTER TABLE task_holds ADD CONSTRAINT task_holds_reason_check
            CHECK (length(trim(reason)) > 0)');
        DB::statement('ALTER TABLE project_holds ADD CONSTRAINT project_holds_reason_check
            CHECK (length(trim(reason)) > 0)');
        DB::statement('ALTER TABLE task_redirects ADD CONSTRAINT task_redirects_reason_check
            CHECK (length(trim(reason)) > 0)');
        DB::statement('ALTER TABLE task_redirects ADD CONSTRAINT task_redirects_no_self
            CHECK (to_step_id IS NULL OR to_step_id <> from_step_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('task_status_history');
        Schema::dropIfExists('task_redirects');
        Schema::dropIfExists('task_holds');
        Schema::dropIfExists('project_holds');
    }
};
