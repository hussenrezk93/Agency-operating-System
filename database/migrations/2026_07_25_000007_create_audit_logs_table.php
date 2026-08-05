<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · audit_logs — append-only, Admin-read-only (BRD §19).
 * user_agent lives inside metadata (the ERD has no user_agent column — kept faithful).
 * On PostgreSQL a trigger blocks UPDATE/DELETE so the log cannot be edited through
 * ANY normal application operation. Never receives passwords, hashes, tokens,
 * or chat message content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_user_id')->nullable()->constrained('users'); // null = system
            $t->string('action');
            $t->string('entity_type');
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->json('metadata');
            $t->ipAddress('ip_address')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['entity_type', 'entity_id']);
            $t->index('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE OR REPLACE FUNCTION audit_logs_append_only() RETURNS trigger AS $$
                BEGIN RAISE EXCEPTION 'audit_logs is append-only'; END;
                $$ LANGUAGE plpgsql");
            DB::statement('CREATE TRIGGER audit_logs_no_update BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only()');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs');
            DB::statement('DROP FUNCTION IF EXISTS audit_logs_append_only');
        }
        Schema::dropIfExists('audit_logs');
    }
};
