<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — outputs can now be an uploaded image, not just a link
 * (the original migration's own comment: "links only, no uploads in v1"). Reuses the
 * existing `url` column rather than adding a new one: for an upload it holds the
 * `public` disk's relative storage path instead of an external URL, and `is_upload`
 * is what tells TaskStepOutput::displayUrl() which one it's looking at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_step_outputs', function (Blueprint $t) {
            $t->boolean('is_upload')->default(false)->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('task_step_outputs', function (Blueprint $t) {
            $t->dropColumn('is_upload');
        });
    }
};
