<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — the same "an output can be an uploaded image, not just a
 * link" widening (task_step_outputs.is_upload) extends to a task's own reference links:
 * whoever creates a task can now attach an image to any of the reference/material link
 * slots instead of a URL. Reuses the existing `url` column exactly like
 * task_step_outputs does — for an upload it holds the `public` disk's relative storage
 * path instead of an external URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_reference_links', function (Blueprint $t) {
            $t->boolean('is_upload')->default(false)->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('task_reference_links', function (Blueprint $t) {
            $t->dropColumn('is_upload');
        });
    }
};
