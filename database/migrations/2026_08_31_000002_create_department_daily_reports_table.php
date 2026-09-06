<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Department Reports — one row per department per day per report type. The
 * unique index is the real concurrency guard: DepartmentReportService::generateForToday()
 * uses firstOrCreate() keyed on exactly these three columns, so a concurrent manual
 * run racing the 15:00 schedule just hits this constraint instead of duplicating rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_daily_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained('departments');
            $t->date('report_date');
            $t->string('type')->default('summary');
            $t->json('auto_summary')->nullable();
            $t->text('details')->nullable();
            $t->timestampTz('submitted_at')->nullable();
            $t->foreignId('submitted_by')->nullable()->constrained('users');
            $t->boolean('is_late')->default(false);
            $t->timestampTz('created_at')->useCurrent();
            $t->unique(['department_id', 'report_date', 'type'], 'ddr_dept_date_type_unique');
            $t->index('report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_daily_reports');
    }
};
