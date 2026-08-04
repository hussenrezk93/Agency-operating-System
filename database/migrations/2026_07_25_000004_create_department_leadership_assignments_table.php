<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · department_leadership_assignments — structure + the three constraints the
 * corrected ERD marks explicitly (database-safe validation ONLY; the temporary-TL
 * handover ENGINE — work transfer, view-only primary, expiry jobs — is deferred, Q10–Q12):
 *   UQ  one active PRIMARY per department
 *   UQ  one active led department per user        (Q10 may later relax this — as-written today)
 *   EX  no overlapping TEMPORARY periods per department
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_leadership_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('department_id')->constrained('departments');
            $t->foreignId('user_id')->constrained('users');
            $t->string('assignment_type');           // primary / temporary
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->timestampTz('ended_early_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->foreignId('assigned_by')->constrained('users');
            $t->timestampTz('created_at')->useCurrent();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX dla_one_active_primary
                ON department_leadership_assignments (department_id)
                WHERE assignment_type = 'primary' AND is_active");
            DB::statement('CREATE UNIQUE INDEX dla_one_active_led_dept
                ON department_leadership_assignments (user_id)
                WHERE is_active');
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement("ALTER TABLE department_leadership_assignments
                ADD CONSTRAINT dla_no_temp_overlap EXCLUDE USING gist (
                    department_id WITH =,
                    daterange(start_date, COALESCE(end_date, 'infinity'::date), '[]') WITH &&
                ) WHERE (assignment_type = 'temporary' AND is_active)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('department_leadership_assignments');
    }
};
