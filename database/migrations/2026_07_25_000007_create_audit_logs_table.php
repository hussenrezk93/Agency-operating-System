<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · audit_logs — append-only, Admin-read-only (BRD §19).
 * user_agent lives inside metadata (the ERD has no user_agent column — kept faithful).
 * A pair of triggers blocks UPDATE and DELETE so the log cannot be edited through ANY
 * normal application operation. MySQL can't combine UPDATE+DELETE into one trigger and
 * has no reusable "function" object a trigger can call, unlike the original PL/pgSQL
 * function + single trigger — hence two separate triggers, each with an inline
 * SIGNAL SQLSTATE body. Never receives passwords, hashes, tokens, or chat message content.
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

        DB::unprepared("
            CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';
            END
        ");
        DB::unprepared("
            CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';
            END
        ");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        Schema::dropIfExists('audit_logs');
    }
};
