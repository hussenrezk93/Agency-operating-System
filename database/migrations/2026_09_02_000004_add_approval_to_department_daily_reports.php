<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — a Manager approves each submitted daily report
 * individually; once approved, that report also becomes visible to the Moderator
 * (DepartmentDailyReport::scopeVisibleTo()), on top of the Content handoff they
 * already see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_daily_reports', function (Blueprint $t) {
            $t->timestampTz('approved_at')->nullable()->after('is_late');
            $t->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('department_daily_reports', function (Blueprint $t) {
            $t->dropConstrainedForeignId('approved_by');
            $t->dropColumn('approved_at');
        });
    }
};
