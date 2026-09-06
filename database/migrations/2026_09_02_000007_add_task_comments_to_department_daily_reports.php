<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — a Team Leader can now attach a short comment to each
 * individual task in that day's activity table (task_comments, keyed by task_id so the
 * Manager can see exactly which task a comment belongs to), plus one general
 * external_note that isn't tied to any specific task.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_daily_reports', function (Blueprint $t) {
            $t->json('task_comments')->nullable()->after('auto_summary');
            $t->text('external_note')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('department_daily_reports', function (Blueprint $t) {
            $t->dropColumn(['task_comments', 'external_note']);
        });
    }
};
