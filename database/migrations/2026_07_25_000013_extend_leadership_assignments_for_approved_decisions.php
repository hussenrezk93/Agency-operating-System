<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * APPROVED DECISIONS Q9, Q13, Q16 — extends the existing table (history preserved,
 * no table was dropped or recreated).
 *
 *  Q9  reason — a temporary TL is appointed ONLY because the primary TL is on leave.
 *  Q13 activation_state — future-dated assignments activate and end automatically;
 *      the scheduler moves pending → active → ended idempotently.
 *  Q16 replaced_by_assignment_id + ended_by — replacing a temporary TL mid-period is
 *      one transactional hand-over, leaving an explicit chain.
 *
 * Q3/Q6 (same department only, one at a time) are enforced by
 * TemporaryLeadershipService + FormRequest + the existing partial unique indexes;
 * a cross-table CHECK is not expressible in standard SQL, so it is validated in the
 * service and covered by tests (documented in ERD-IMPACT-NOTES.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_leadership_assignments', function (Blueprint $t) {
            $t->string('activation_state')->default('active')->after('is_active');
            $t->text('reason')->nullable()->after('activation_state');
            $t->foreignId('ended_by')->nullable()->after('assigned_by')->constrained('users');
            $t->foreignId('replaced_by_assignment_id')->nullable()->after('ended_by')
                ->constrained('department_leadership_assignments');
            $t->index(['activation_state', 'start_date', 'end_date']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE department_leadership_assignments
                ADD CONSTRAINT dla_activation_state
                CHECK (activation_state IN ('pending','active','ended'))");
            DB::statement("ALTER TABLE department_leadership_assignments
                ADD CONSTRAINT dla_type CHECK (assignment_type IN ('primary','temporary'))");
            // A temporary appointment is always time-boxed (BRD §12).
            DB::statement("ALTER TABLE department_leadership_assignments
                ADD CONSTRAINT dla_temp_has_end
                CHECK (assignment_type <> 'temporary' OR end_date IS NOT NULL)");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (['dla_activation_state', 'dla_type', 'dla_temp_has_end'] as $c) {
                DB::statement("ALTER TABLE department_leadership_assignments DROP CONSTRAINT IF EXISTS {$c}");
            }
        }
        Schema::table('department_leadership_assignments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('replaced_by_assignment_id');
            $t->dropConstrainedForeignId('ended_by');
            $t->dropColumn(['activation_state', 'reason']);
        });
    }
};
