<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read receipts ("seen" ticks + timestamp) for chat, tracked as a per-member watermark
 * rather than a row per message-per-reader — a member has read everything up to
 * `last_read_message_id`, the same model `notifications`/`notification_deliveries` use for
 * in-app read state, just scoped to a conversation instead of a single row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_members', function (Blueprint $t): void {
            $t->foreignId('last_read_message_id')->nullable()->after('left_at')
                ->constrained('chat_messages')->nullOnDelete();
            $t->timestampTz('last_read_at')->nullable()->after('last_read_message_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_members', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('last_read_message_id');
            $t->dropColumn('last_read_at');
        });
    }
};
