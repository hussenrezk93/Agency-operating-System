<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 1B · ERD v1.2 — tasks + task_reference_links.
 *
 * A task may belong to a project or stand alone (BRD §8). `current_step_id` is a
 * denormalised pointer to the step that currently owns the work; the FK is added after
 * task_steps exists, because the two tables reference each other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $t) {
            $t->id();
            $t->string('task_code')->unique();                  // TSK-2026-00344
            $t->foreignId('project_id')->nullable()->constrained('projects');
            $t->string('title');                                // stored in English (BRD rule)
            $t->text('brief');
            $t->text('notes')->nullable();
            $t->string('priority')->default('medium');
            $t->string('lifecycle_status')->default('draft');
            $t->foreignId('created_by')->constrained('users');
            $t->unsignedBigInteger('current_step_id')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('updated_at')->nullable();
            $t->timestampTz('completed_at')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users');
            $t->timestampTz('cancelled_at')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users');
            $t->text('cancelled_reason')->nullable();
            $t->index(['lifecycle_status', 'priority']);
            $t->index('project_id');
        });

        Schema::create('task_reference_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $t->foreignId('added_by')->constrained('users');
            $t->text('url');                                    // links only — no file storage in v1
            $t->string('label')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index('task_id');
        });

        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_priority_check
            CHECK (priority IN ('low','medium','high','urgent'))");
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_lifecycle_check
            CHECK (lifecycle_status IN ('draft','active','on_hold','completed','cancelled'))");
        // A cancelled task must record why (BRD §10, mandatory reason).
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_cancel_reason_check
            CHECK (lifecycle_status <> 'cancelled' OR cancelled_reason IS NOT NULL)");
        // A completed task must record when and by whom (audit integrity, BRD §22.6).
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_completed_meta_check
            CHECK (lifecycle_status <> 'completed'
                   OR (completed_at IS NOT NULL AND completed_by IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reference_links');
        Schema::dropIfExists('tasks');
    }
};
