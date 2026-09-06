<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — the Manager can attach a bonus or a deduction (a money
 * amount, always with a written reason) to one person for one month, shown next to
 * that month's performance on the reports page.
 *
 * Each adjustment is its own row rather than one editable running total per person:
 * every amount keeps the reason and the Manager who entered it, so the month reads as
 * a list of decisions instead of a number nobody can account for. Deliberately does
 * NOT touch monthly_performance_snapshots — the score stays a pure deadline
 * measurement (BRD §17), and money sits beside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->date('month_start');
            $t->string('type');                       // bonus | deduction
            $t->decimal('amount', 10, 2);
            $t->text('reason');
            $t->foreignId('created_by')->constrained('users');
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['month_start', 'user_id']);
        });

        DB::statement("ALTER TABLE performance_adjustments ADD CONSTRAINT pa_type_check
            CHECK (type IN ('bonus','deduction'))");
        DB::statement('ALTER TABLE performance_adjustments ADD CONSTRAINT pa_amount_check
            CHECK (amount > 0)');
        DB::statement('ALTER TABLE performance_adjustments ADD CONSTRAINT pa_reason_check
            CHECK (length(trim(reason)) > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_adjustments');
    }
};
