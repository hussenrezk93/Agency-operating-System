<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — the Manager's payroll and spending book.
 *
 * Three tables, deliberately separate:
 *
 *   employee_salaries  ONE row per person: their standing monthly salary. It is not a
 *                      column on `users` on purpose — `users` rows are loaded and
 *                      serialized all over this app (chat, task lists, JSON endpoints),
 *                      and a salary must never ride along by accident. Changes are
 *                      audited, so the audit log carries the history.
 *
 *   payroll_periods    ONE row per person per month: the window the Manager opened for
 *                      them (they set both dates by hand) and, once closed, a frozen
 *                      snapshot of what was actually paid. The snapshot matters: a
 *                      bonus added or a salary raised next month must never rewrite a
 *                      month already paid out.
 *
 *   expenses           Free-text spending (transport, maintenance, anything), one row
 *                      per item, dated so the monthly total is a plain date range.
 *
 * Bonuses and deductions are NOT duplicated here — they already live in
 * performance_adjustments, keyed by the same month_start, and payroll reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->decimal('monthly_amount', 12, 2);
            $t->text('note')->nullable();
            $t->foreignId('updated_by')->constrained('users');
            $t->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE employee_salaries ADD CONSTRAINT es_amount_check
            CHECK (monthly_amount >= 0)');

        Schema::create('payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('month_start');
            $t->date('period_start');
            $t->date('period_end');
            $t->decimal('base_amount', 12, 2);
            $t->string('status')->default('open');       // open | closed
            // Frozen at close; null while the period is still open, where the totals are
            // read live from performance_adjustments instead.
            $t->decimal('bonus_total', 12, 2)->nullable();
            $t->decimal('deduction_total', 12, 2)->nullable();
            $t->decimal('net_amount', 12, 2)->nullable();
            $t->text('note')->nullable();
            $t->foreignId('opened_by')->constrained('users');
            $t->timestampTz('opened_at')->useCurrent();
            $t->foreignId('closed_by')->nullable()->constrained('users');
            $t->timestampTz('closed_at')->nullable();
            $t->unique(['user_id', 'month_start']);
            $t->index('month_start');
        });

        DB::statement("ALTER TABLE payroll_periods ADD CONSTRAINT pp_status_check
            CHECK (status IN ('open','closed'))");
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT pp_amount_check
            CHECK (base_amount >= 0)');
        DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT pp_dates_check
            CHECK (period_end >= period_start)');

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->date('spent_on');
            $t->text('description');
            $t->decimal('amount', 12, 2);
            $t->foreignId('created_by')->constrained('users');
            $t->timestampTz('created_at')->useCurrent();
            $t->index('spent_on');
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT ex_amount_check
            CHECK (amount > 0)');
        DB::statement('ALTER TABLE expenses ADD CONSTRAINT ex_description_check
            CHECK (length(trim(description)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employee_salaries');
    }
};
