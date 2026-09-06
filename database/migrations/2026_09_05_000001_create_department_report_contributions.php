<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — Sales writes its daily report piece by piece: every member
 * writes their own part, and the system stacks the parts into the one report the Manager
 * already reads. The merge is VERBATIM: nothing rewrites, trims or summarises what a
 * person typed, it only puts their name above it.
 *
 * One row per person per report. `department_daily_reports.details` still holds the
 * finished text, so every reader downstream — the Manager's page, the printout, the
 * approval flow — carries on unchanged; this table is where the pieces live until the
 * Team Leader submits.
 *
 * The Sales department is tagged with special_role=sales here for the same reason every
 * other rule in this app keys off special_role: `name` is freely Admin-editable, and
 * renaming a department must not silently switch its reporting off.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_report_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained('department_daily_reports')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->timestampTz('submitted_at')->useCurrent();
            $t->timestampTz('updated_at')->nullable();
            $t->unique(['report_id', 'user_id']);
        });

        DB::statement('ALTER TABLE department_report_contributions ADD CONSTRAINT drc_body_check
            CHECK (length(trim(body)) > 0)');

        DB::table('departments')
            ->whereRaw('LOWER(name) = ?', ['sales'])
            ->whereNull('special_role')
            ->update(['special_role' => 'sales']);
    }

    public function down(): void
    {
        DB::table('departments')->where('special_role', 'sales')->update(['special_role' => null]);

        Schema::dropIfExists('department_report_contributions');
    }
};
