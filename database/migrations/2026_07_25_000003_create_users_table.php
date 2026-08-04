<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · users — every field below verified against the corrected ERD:
 * role_id FK, department_id FK nullable (ERD models membership as a direct FK — one
 * department per user, BRD §6), full_name, username unique, password_hash,
 * status active/inactive/on_leave, must_change_password, personal_email NOT NULL
 * unique (CR-001), email_verified_at nullable, timestamps.
 * The email VERIFICATION WORKFLOW is deferred (columns are schema, not behavior).
 * `pending_email` is added by a later migration under approved decision Q24.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->foreignId('role_id')->constrained('roles');
            $t->foreignId('department_id')->nullable()->constrained('departments');
            $t->string('full_name');
            $t->string('username')->unique();
            $t->text('password_hash');
            $t->string('status')->default('active');
            $t->boolean('must_change_password')->default(true);
            $t->string('personal_email')->unique();
            $t->timestampTz('email_verified_at')->nullable();
            $t->timestampsTz();
            $t->index(['role_id', 'status']);
        });

        // Session table for the database session driver (framework standard, not ERD).
        // Guarded: the stock Laravel create_users_table migration also provides it.
        Schema::hasTable('sessions') or Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
