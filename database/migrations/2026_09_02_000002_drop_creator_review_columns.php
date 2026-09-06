<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product decision 2026-09 — "creator review" (the task creator's optional sign-off
 * gate, added in 2026_08_28_000001_add_creator_review_to_tasks_and_task_steps.php) is
 * removed entirely, replaced by the mandatory Manager-review stage in WorkflowStatus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('creator_review_required');
        });

        Schema::table('task_steps', function (Blueprint $table): void {
            $table->dropColumn(['creator_reviewed_at', 'creator_review_comment']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->boolean('creator_review_required')->default(false);
        });

        Schema::table('task_steps', function (Blueprint $table): void {
            $table->timestampTz('creator_reviewed_at')->nullable();
            $table->text('creator_review_comment')->nullable();
        });
    }
};
