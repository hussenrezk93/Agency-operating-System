<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * APPROVED DECISIONS Q24 + Q2 — ERD amendment (see ERD-IMPACT-NOTES.md).
 *
 * Q24 (pending email): changing a verified personal email must NOT break the channel.
 *   The verified address stays active; the new address is held as `pending_email`
 *   until its verification succeeds, then it is promoted.
 *
 * Q2 (temporary-TL role transition): `role_id` becomes the EFFECTIVE role, while
 *   `base_role_id` preserves the substantive role the user returns to. It is NULL
 *   whenever no temporary elevation is active, so the original role value can never
 *   be lost by a destructive overwrite. Full history lives in user_role_transitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('pending_email')->nullable()->after('personal_email');
            $t->timestampTz('pending_email_requested_at')->nullable()->after('pending_email');
            $t->foreignId('base_role_id')->nullable()->after('role_id')->constrained('roles');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('base_role_id');
            $t->dropColumn(['pending_email', 'pending_email_requested_at']);
        });
    }
};
