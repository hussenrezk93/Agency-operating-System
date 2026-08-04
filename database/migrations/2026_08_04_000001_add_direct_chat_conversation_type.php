<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Company directory / general direct messages — a later-approved, deliberate extension
 * of BRD §14's five conversation shapes with a sixth: open person-to-person messaging,
 * including Admin. Widens `chat_conv_type_check` rather than editing the original
 * (approved) Phase 2 migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE chat_conversations DROP CONSTRAINT chat_conv_type_check');

            DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conv_type_check
                CHECK (type IN ('employee_tl', 'department_group', 'direct_tl', 'all_tls', 'manager_tls', 'direct'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE chat_conversations DROP CONSTRAINT chat_conv_type_check');

            DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conv_type_check
                CHECK (type IN ('employee_tl', 'department_group', 'direct_tl', 'all_tls', 'manager_tls'))");
        }
    }
};
