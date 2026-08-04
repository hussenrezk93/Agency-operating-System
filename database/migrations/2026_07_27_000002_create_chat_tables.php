<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2 (completing the ERD) — internal chat and the two-hour email digest.
 *
 * Schema only. The conversation UI, membership resolution and the digest scheduler are
 * Phase 8; nothing here sends anything.
 *
 * BRD §14 — THE DELETION RULE IS ENFORCED BY THE DATABASE, NOT ONLY BY CODE.
 * When the sender deletes a message it disappears for everyone and the CONTENT IS GONE:
 * `body` and `link_url` are cleared. The audit log records that a deletion happened, by
 * whom and when — but never the text (BRD §19). `chat_msg_content_check` makes the two
 * states the only ones representable:
 *   · live      → deleted_at IS NULL  AND at least one of body/link_url present
 *   · deleted   → deleted_at NOT NULL AND both body and link_url NULL
 * A "soft delete" that quietly keeps the text in the column would satisfy the UI and
 * violate the BRD, so the constraint refuses to store it.
 *
 * BRD §11.1 / §14 — chat notifications are immediate in-app but BATCHED to email every two
 * hours. `chat_digest_batches` is that window, and `chat_digest_batch_messages` records
 * exactly which messages a batch covered, so a message is never counted in two digests and
 * an empty window is never sent (BRD §22.18).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $t): void {
            $t->id();
            // employee_tl | department_group | direct_tl | all_tls | manager_tls
            $t->string('type', 32);
            // Set for department_group; null for the cross-department conversations.
            $t->foreignId('department_id')->nullable()->constrained('departments');
            $t->string('title')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            $t->index(['type', 'department_id']);
        });

        Schema::create('chat_members', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestampTz('joined_at')->useCurrent();
            $t->timestampTz('left_at')->nullable();

            $t->unique(['conversation_id', 'user_id'], 'chat_members_unique');
            $t->index(['user_id', 'left_at']);
        });

        Schema::create('chat_messages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users');
            $t->text('body')->nullable();          // cleared on delete
            $t->text('link_url')->nullable();      // text and links only (BRD §14)
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('deleted_at')->nullable();
            $t->foreignId('deleted_by')->nullable()->constrained('users');

            // The conversation view, newest last.
            $t->index(['conversation_id', 'created_at']);
        });

        Schema::create('chat_digest_batches', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('notification_id')->nullable()->constrained('notifications');
            $t->timestampTz('window_start');
            $t->timestampTz('window_end');
            $t->integer('message_count');
            $t->string('status', 16)->default('queued'); // queued|sent|failed
            $t->timestampTz('sent_at')->nullable();
            $t->text('error')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            $t->index(['user_id', 'window_end']);
        });

        // Which messages a batch covered. Composite primary key: a message can belong to
        // one batch per user at most, which is what stops a double-send.
        Schema::create('chat_digest_batch_messages', function (Blueprint $t): void {
            $t->foreignId('batch_id')->constrained('chat_digest_batches')->cascadeOnDelete();
            $t->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();
            $t->timestampTz('added_at')->useCurrent();

            $t->primary(['batch_id', 'message_id']);
            $t->index('message_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conv_type_check
                CHECK (type IN ('employee_tl', 'department_group', 'direct_tl', 'all_tls', 'manager_tls'))");

            // A department group belongs to a department; the others must not claim one.
            DB::statement("ALTER TABLE chat_conversations ADD CONSTRAINT chat_conv_department_check
                CHECK ((type = 'department_group') = (department_id IS NOT NULL))");

            // BRD §14 — the two representable states, and nothing between them.
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT chat_msg_content_check
                CHECK (
                    (deleted_at IS NULL  AND (body IS NOT NULL OR link_url IS NOT NULL))
                    OR
                    (deleted_at IS NOT NULL AND body IS NULL AND link_url IS NULL)
                )');

            // A deleted message records who deleted it.
            DB::statement('ALTER TABLE chat_messages ADD CONSTRAINT chat_msg_deleted_by_check
                CHECK ((deleted_at IS NULL) = (deleted_by IS NULL))');

            DB::statement("ALTER TABLE chat_digest_batches ADD CONSTRAINT chat_digest_status_check
                CHECK (status IN ('queued', 'sent', 'failed'))");

            // BRD §22.18 — an empty window is never sent, so a batch always covers messages.
            DB::statement('ALTER TABLE chat_digest_batches ADD CONSTRAINT chat_digest_count_check
                CHECK (message_count > 0)');

            DB::statement('ALTER TABLE chat_digest_batches ADD CONSTRAINT chat_digest_window_check
                CHECK (window_end > window_start)');

            DB::statement('ALTER TABLE chat_members ADD CONSTRAINT chat_members_left_check
                CHECK (left_at IS NULL OR left_at >= joined_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_digest_batch_messages');
        Schema::dropIfExists('chat_digest_batches');
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_members');
        Schema::dropIfExists('chat_conversations');
    }
};
