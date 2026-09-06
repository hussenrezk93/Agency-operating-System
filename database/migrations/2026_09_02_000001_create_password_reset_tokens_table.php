<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service password reset (CR-002, 2026-09) — schema is a structural mirror of
 * `email_verification_tokens` (see 2026_07_27_000001_create_notification_tables.php):
 * only the HASH of the token is stored, so a database leak hands out nothing usable.
 * `expires_at` stays nullable and is always set explicitly by the app for the same
 * reason as that table — MySQL/MariaDB's legacy "first not-null/no-default TIMESTAMP
 * column" rule would otherwise silently attach `ON UPDATE CURRENT_TIMESTAMP`, which
 * would overwrite `expires_at` back to "now" the moment a token is consumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('token_hash');
            $t->timestampTz('expires_at')->nullable();
            $t->timestampTz('consumed_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            $t->index(['user_id', 'consumed_at']);
            $t->unique('token_hash', 'prt_token_hash_unique');
        });

        DB::statement('ALTER TABLE password_reset_tokens ADD CONSTRAINT prt_expiry_check
            CHECK (expires_at > created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
