<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · department_routes — the Admin-managed matrix of which department may send
 * work to which (BRD §15). Structural + Admin configuration only; the ROUTING DECISION
 * at "Send to next department" belongs to the task workflow engine (Phase 1B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_routes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('from_department_id')->constrained('departments');
            $t->foreignId('to_department_id')->constrained('departments');
            $t->boolean('is_allowed')->default(true);
            $t->foreignId('updated_by')->constrained('users');
            $t->timestampTz('updated_at')->useCurrent();
            $t->unique(['from_department_id', 'to_department_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE department_routes ADD CONSTRAINT dr_no_self_route
                CHECK (from_department_id <> to_department_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_routes');
    }
};
