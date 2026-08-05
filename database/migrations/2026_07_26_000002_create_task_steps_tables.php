<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 1B · ERD v1.2 — task_steps + task_step_assignments.
 *
 * One step = one department's turn. Two departments never work the same step in parallel
 * (BRD §2); parallel work means separate tasks in the same project.
 *
 * APPROVED DECISION Q8 — the authoritative deadline lives on the STEP
 * (`current_start_date` / `current_due_at`), not only on the assignment, so reassigning
 * an employee cannot silently move the department's commitment. The assignment keeps its
 * own dates for history. `current_due_at` is a timestamp fixed at 23:59 Africa/Cairo of
 * the due date (BRD §11).
 *
 * APPROVED DECISION Q19/Q20 — `first_seen_at` is written exactly once, never reversed,
 * and is readable only by the department's effective Team Leader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_steps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('department_id')->constrained('departments');
            $t->integer('sequence_no');
            $t->string('workflow_status')->default('waiting_assignment');
            $t->string('deadline_status')->default('not_started'); // derived; DeadlineService owns it
            $t->date('current_start_date')->nullable();
            $t->timestampTz('current_due_at')->nullable();          // 23:59 Africa/Cairo
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('submitted_at')->nullable();
            $t->timestampTz('approved_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->unique(['task_id', 'sequence_no']);
            $t->index(['department_id', 'workflow_status']);
            $t->index('deadline_status');
        });

        Schema::table('tasks', function (Blueprint $t) {
            $t->foreign('current_step_id')->references('id')->on('task_steps')->nullOnDelete();
        });

        Schema::create('task_step_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_step_id')->constrained('task_steps')->cascadeOnDelete();
            $t->foreignId('assignee_id')->constrained('users');   // ONE employee, or the TL itself
            $t->foreignId('assigned_by')->constrained('users');
            $t->boolean('is_self_assigned')->default(false);      // Q12 → reviewed by a Manager
            $t->date('start_date');
            $t->date('due_date');
            $t->timestampTz('first_seen_at')->nullable();         // Q19: written once, never reset
            $t->timestampTz('assigned_at')->useCurrent();
            $t->timestampTz('ended_at')->nullable();
            $t->text('end_reason')->nullable();
            $t->index(['assignee_id', 'ended_at']);

            // NULL unless this assignment is still open — collapses to "exactly one open
            // assignment per step" (BRD §9.3). MySQL has no partial/filtered index, so
            // this generated column + a plain unique index stands in for what was a
            // PostgreSQL `CREATE UNIQUE INDEX ... WHERE ended_at IS NULL`.
            $t->unsignedBigInteger('open_task_step_id')->nullable()
                ->virtualAs('CASE WHEN ended_at IS NULL THEN task_step_id END');
            $t->unique('open_task_step_id', 'tsa_one_open_assignment');
        });

        DB::statement("ALTER TABLE task_steps ADD CONSTRAINT task_steps_workflow_check
            CHECK (workflow_status IN ('waiting_assignment','in_progress','under_review',
                   'changes_requested','approved','redirected','cancelled'))");
        DB::statement("ALTER TABLE task_steps ADD CONSTRAINT task_steps_deadline_check
            CHECK (deadline_status IN ('not_started','on_time','due_soon','overdue','paused','closed'))");
        DB::statement('ALTER TABLE task_steps ADD CONSTRAINT task_steps_sequence_check
            CHECK (sequence_no > 0)');
        DB::statement('ALTER TABLE task_step_assignments ADD CONSTRAINT tsa_date_order_check
            CHECK (due_date >= start_date)');
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $t) {
            $t->dropForeign(['current_step_id']);
        });
        Schema::dropIfExists('task_step_assignments');
        Schema::dropIfExists('task_steps');
    }
};
