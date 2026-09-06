<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Outputs stay immutable (BRD §13, unchanged) — this does not add editing or hard
 * deletion. It adds a way to mark a link the assignee no longer wants counted (added by
 * mistake, or superseded by a better one before the reviewer decides) without erasing
 * the row: the audit trail keeps "added, then removed" instead of losing the record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_step_outputs', function (Blueprint $table) {
            $table->timestampTz('removed_at')->nullable()->after('is_final');
        });
    }

    public function down(): void
    {
        Schema::table('task_step_outputs', function (Blueprint $table) {
            $table->dropColumn('removed_at');
        });
    }
};
