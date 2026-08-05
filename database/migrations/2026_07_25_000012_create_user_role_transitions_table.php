<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * APPROVED DECISION Q2 — NEW ENTITY (ERD amendment).
 * Append-style history of every effective-role change, so a temporary elevation is
 * always traceable and reversible:
 *   elevation → from Employee to Team Leader, linked to the leadership assignment
 *   restoration → back to the preserved base role when the period ends / is cut short
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_transitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users');
            $t->foreignId('from_role_id')->constrained('roles');
            $t->foreignId('to_role_id')->constrained('roles');
            $t->string('transition_type');   // elevation / restoration
            $t->string('reason');            // temporary_tl_start / temporary_tl_end /
            // temporary_tl_early_end / temporary_tl_replaced
            $t->foreignId('leadership_assignment_id')->nullable()
                ->constrained('department_leadership_assignments');
            $t->foreignId('performed_by')->nullable()->constrained('users'); // null = scheduler
            $t->timestampTz('effective_at')->useCurrent();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['user_id', 'effective_at']);
        });

        DB::statement("ALTER TABLE user_role_transitions ADD CONSTRAINT urt_type
            CHECK (transition_type IN ('elevation','restoration'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_transitions');
    }
};
