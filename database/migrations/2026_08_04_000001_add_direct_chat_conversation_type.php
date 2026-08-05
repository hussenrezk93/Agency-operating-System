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
        // DROP CONSTRAINT (not DROP CHECK): MySQL 8.0.19+ accepts it as an alias, and
        // it's the only form MariaDB understands for a CHECK constraint — one statement
        // that works on both.
        DB::statement('ALTER TABLE chat_conversations DROP CONSTRAINT chat_conv_type_check');

        DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conv_type_check
            CHECK (type IN ('employee_tl', 'department_group', 'direct_tl', 'all_tls', 'manager_tls', 'direct'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE chat_conversations DROP CONSTRAINT chat_conv_type_check');

        DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conv_type_check
            CHECK (type IN ('employee_tl', 'department_group', 'direct_tl', 'all_tls', 'manager_tls'))");
    }
};
