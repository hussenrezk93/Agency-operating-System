<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product decision 2026-09 — WorkflowStatus gained PendingManagerReview (the mandatory
 * Manager review stage between UnderReview and Approved). The DB-level CHECK constraint
 * from 2026_07_26_000002_create_task_steps_tables.php enumerates every valid value
 * explicitly, so it must be dropped and recreated to include the new one — MySQL has no
 * ALTER CONSTRAINT.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE task_steps DROP CONSTRAINT task_steps_workflow_check');
        DB::statement("ALTER TABLE task_steps ADD CONSTRAINT task_steps_workflow_check
            CHECK (workflow_status IN ('waiting_assignment','in_progress','under_review',
                   'pending_manager_review','changes_requested','approved','redirected','cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE task_steps DROP CONSTRAINT task_steps_workflow_check');
        DB::statement("ALTER TABLE task_steps ADD CONSTRAINT task_steps_workflow_check
            CHECK (workflow_status IN ('waiting_assignment','in_progress','under_review',
                   'changes_requested','approved','redirected','cancelled'))");
    }
};
