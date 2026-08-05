<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · department_leadership_assignments — structure + the two constraints MySQL
 * can express at the schema level (database-safe validation ONLY; the temporary-TL
 * handover ENGINE — work transfer, view-only primary, expiry jobs — is deferred, Q10–Q12):
 *   UQ  one active PRIMARY per department
 *   UQ  one active led department per user        (Q10 may later relax this — as-written today)
 *
 * A third rule — no overlapping TEMPORARY periods per department — was originally a
 * PostgreSQL `EXCLUDE USING gist` constraint. MySQL has no equivalent construct at all
 * (no range types, no exclusion indexes), so on the MySQL cutover that rule moved fully
 * to the application layer: TemporaryLeadershipService::assertEligible(), under a
 * lockForUpdate() transaction to close the same race window the DB constraint used to.
 *
 * The two UNIQUE rules above have no direct MySQL equivalent either (MySQL has no partial/
 * filtered index), so each is expressed as a generated (virtual) column that evaluates to
 * the uniqued value only when the original condition holds, and NULL otherwise — MySQL/
 * InnoDB treats every NULL as distinct, so excluded rows never collide with each other.
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

            // NULL unless this row is the active primary leader of its department —
            // collapses to the plain "one active primary per department" rule.
            $t->unsignedBigInteger('active_primary_department_id')->nullable()
                ->virtualAs("CASE WHEN assignment_type = 'primary' AND is_active THEN department_id END");
            $t->unique('active_primary_department_id', 'dla_one_active_primary');

            // NULL unless this row is currently active — collapses to "one active
            // leadership row per user" (primary or temporary — Q10 may relax this later).
            $t->unsignedBigInteger('active_led_user_id')->nullable()
                ->virtualAs('CASE WHEN is_active THEN user_id END');
            $t->unique('active_led_user_id', 'dla_one_active_led_dept');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_leadership_assignments');
    }
};
