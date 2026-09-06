<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product decision 2026-09 — Graphic's work is reviewed by Content before it reaches
 * the Manager, which needs a new workflow_status. MySQL has no ALTER CONSTRAINT, so the
 * CHECK that enumerates every legal value has to be dropped and rebuilt with the new one
 * (same as the pending_manager_review migration did).
 *
 * The Graphic department is tagged with special_role=graphic here too: `name` is freely
 * Admin-editable, so the routing rule keys off the stable role, never the label — the
 * exact reason DepartmentSpecialRole exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE task_steps DROP CONSTRAINT task_steps_workflow_check');
        DB::statement("ALTER TABLE task_steps ADD CONSTRAINT task_steps_workflow_check
            CHECK (workflow_status IN ('waiting_assignment','in_progress','under_review',
                   'pending_content_review','pending_manager_review','changes_requested',
                   'approved','redirected','cancelled'))");

        DB::table('departments')
            ->whereRaw('LOWER(name) = ?', ['graphic'])
            ->whereNull('special_role')
            ->update(['special_role' => 'graphic']);
    }

    public function down(): void
    {
        DB::table('departments')->where('special_role', 'graphic')->update(['special_role' => null]);

        DB::statement('ALTER TABLE task_steps DROP CONSTRAINT task_steps_workflow_check');
        DB::statement("ALTER TABLE task_steps ADD CONSTRAINT task_steps_workflow_check
            CHECK (workflow_status IN ('waiting_assignment','in_progress','under_review',
                   'pending_manager_review','changes_requested','approved','redirected','cancelled'))");
    }
};
