<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · department_output_access — Admin rules for which department's TL may view
 * another department's outputs (BRD §15 "قاعدة مشاهدة المخرجات").
 * Employees never receive this general right; their in-task visibility is governed by
 * approved decision Q26 (final approved outputs of previous completed steps only),
 * which is enforced by the task workflow engine in Phase 1B.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_output_access', function (Blueprint $t) {
            $t->id();
            $t->foreignId('viewer_department_id')->constrained('departments');
            $t->foreignId('source_department_id')->constrained('departments');
            $t->string('scope')->default('all_outputs'); // all_outputs / final_only (Q25)
            $t->boolean('is_allowed')->default(true);
            $t->foreignId('updated_by')->constrained('users');
            $t->timestampTz('updated_at')->useCurrent();
            $t->unique(['viewer_department_id', 'source_department_id'], 'doa_viewer_source_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE department_output_access ADD CONSTRAINT doa_no_self
                CHECK (viewer_department_id <> source_department_id)');
            DB::statement("ALTER TABLE department_output_access ADD CONSTRAINT doa_scope
                CHECK (scope IN ('all_outputs','final_only'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_output_access');
    }
};
