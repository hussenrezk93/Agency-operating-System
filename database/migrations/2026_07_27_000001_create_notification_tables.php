<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2 (completing the ERD) — notifications, per-channel delivery and email verification.
 *
 * These are the ERD v1.2 tables that CR-001 introduced. The SCHEMA is laid down now, in the
 * database phase, exactly as the Laravel Development Phases guide requires ("تحويل الـERD
 * إلى قاعدة بيانات حقيقية"). The sending logic, the queue worker and the templates belong to
 * Phase 7 and are NOT part of this migration.
 *
 * THE CENTRAL DESIGN POINT (ERD §C.1)
 * `notifications` holds the LOGICAL event exactly once. `notification_deliveries` holds one
 * row per CHANNEL for that event. This separation is what makes BRD §11.1 true:
 *   · the in-app notification is mandatory and is the official record;
 *   · email is a best-effort assisting channel, so its failure is recorded against the
 *     delivery row and never against the event — a bounced email cannot erase the fact that
 *     the user was notified, and cannot block a workflow transition (BRD §22.19);
 *   · a future channel (SMS, push) is a new delivery row, not a change to this model.
 *
 * BRD §18.1 — the verification link lives 24 hours. Only the HASH of the token is stored, so
 * a database leak does not hand out working verification links.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type');                       // e.g. task.assigned, project.invite
            $t->string('title');
            $t->text('body');
            // Polymorphic-by-hand: the ERD keeps this loose so a notification can point at a
            // task, a step, a project or nothing at all without a constraint per entity.
            $t->string('entity_type')->nullable();
            $t->unsignedBigInteger('entity_id')->nullable();
            $t->boolean('is_read')->default(false);
            $t->timestampTz('created_at')->useCurrent();
            $t->timestampTz('read_at')->nullable();

            // The notification bell: unread first, newest first, for one user.
            $t->index(['user_id', 'is_read', 'created_at']);
            $t->index(['entity_type', 'entity_id']);
        });

        Schema::create('notification_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $t->string('channel', 16);                // in_app | email
            $t->string('status', 16)->default('queued'); // queued|sent|failed|bounced
            $t->string('provider_message_id')->nullable();
            $t->text('error')->nullable();
            $t->integer('attempt_count')->default(0);
            $t->timestampTz('last_attempt_at')->nullable();
            $t->timestampTz('next_attempt_at')->nullable();
            $t->timestampTz('sent_at')->nullable();
            $t->timestampTz('bounced_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            // One delivery row per channel per event — makes retry idempotent.
            $t->unique(['notification_id', 'channel'], 'nd_notification_channel_unique');
            // The queue worker's claim query.
            $t->index(['status', 'next_attempt_at'], 'nd_retry_index');
        });

        Schema::create('email_verification_tokens', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('token_hash');                 // hash only — never the raw token
            // ->nullable(): MySQL/MariaDB's legacy "first not-null/no-default TIMESTAMP
            // column with no explicit attribute" rule was implicitly giving this column
            // DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP — silently overwriting
            // expires_at back to "now" on every UPDATE (e.g. consuming the token), which
            // then failed evt_expiry_check. Always set explicitly by the app regardless.
            $t->timestampTz('expires_at')->nullable();
            $t->timestampTz('consumed_at')->nullable();
            $t->timestampTz('created_at')->useCurrent();

            $t->index(['user_id', 'consumed_at']);
            $t->unique('token_hash', 'evt_token_hash_unique');
        });

        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT nd_channel_check
            CHECK (channel IN ('in_app', 'email'))");
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT nd_status_check
            CHECK (status IN ('queued', 'sent', 'failed', 'bounced'))");

        // A row that claims to be sent must say when. Prevents a 'sent' with no timestamp
        // being counted as delivered by the Phase 9 reports.
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT nd_sent_at_check
            CHECK (status <> 'sent' OR sent_at IS NOT NULL)");
        DB::statement("ALTER TABLE notification_deliveries ADD CONSTRAINT nd_bounced_at_check
            CHECK (status <> 'bounced' OR bounced_at IS NOT NULL)");

        // A read notification must carry the moment it was read (and the reverse).
        DB::statement('ALTER TABLE notifications ADD CONSTRAINT notifications_read_check
            CHECK (is_read = (read_at IS NOT NULL))');

        DB::statement('ALTER TABLE email_verification_tokens ADD CONSTRAINT evt_expiry_check
            CHECK (expires_at > created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_tokens');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
    }
};
