<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Confirmed enum vocabularies enforced at the database level, so an invalid status can
 * never be written by any code path (BRD non-functional: reliability).
 * projects.status includes 'on_hold' per APPROVED CHANGE REQUEST Q23 — the STATUS
 * exists now; the pausing BEHAVIOR (task pausing, deadline accounting, resume) is
 * deliberately deferred to Phase 1B and is not implemented anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check
            CHECK (status IN ('active','inactive','on_leave'))");
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_status_check
            CHECK (status IN ('active','inactive'))");
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_status_check
            CHECK (status IN ('active','on_hold','completed','cancelled'))");
        // A closed project must record who closed it and when (audit integrity).
        DB::statement("ALTER TABLE projects ADD CONSTRAINT projects_cancel_reason_check
            CHECK (status <> 'cancelled' OR cancelled_reason IS NOT NULL)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ([
            'users' => ['users_status_check'],
            'clients' => ['clients_status_check'],
            'projects' => ['projects_status_check', 'projects_cancel_reason_check'],
        ] as $table => $constraints) {
            foreach ($constraints as $c) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$c}");
            }
        }
    }
};
