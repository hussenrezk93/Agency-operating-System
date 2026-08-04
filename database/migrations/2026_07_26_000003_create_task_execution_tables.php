<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 1B · ERD v1.2 + APPROVED DECISION Q25 — execution artefacts.
 *
 * Q25 AMENDMENT (documented in ERD-IMPACT-NOTES.md): "every link in an approved
 * submission is final" cannot be derived from "the latest row", so outputs are grouped by
 * `submission_no`. When a submission is approved, ALL of its outputs are marked final.
 * Outputs are immutable: a correction adds a new row and points the old one at it through
 * `superseded_by_output_id`.
 *
 * Comments are stage-private (BRD §13): visible to the assignee and the department's
 * effective Team Leader only — never to the next department.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_step_outputs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_step_id')->constrained('task_steps')->cascadeOnDelete();
            $t->foreignId('added_by')->constrained('users');
            $t->integer('submission_no')->default(1);            // Q25 grouping
            $t->text('url');                                      // links only, no uploads in v1
            $t->string('label')->nullable();
            $t->boolean('is_final')->default(false);              // set for the whole approved group
            $t->unsignedBigInteger('superseded_by_output_id')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index(['task_step_id', 'submission_no']);
            $t->index('is_final');
        });

        Schema::table('task_step_outputs', function (Blueprint $t) {
            $t->foreign('superseded_by_output_id')->references('id')
                ->on('task_step_outputs')->nullOnDelete();
        });

        Schema::create('task_step_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_step_id')->constrained('task_steps')->cascadeOnDelete();
            $t->foreignId('author_id')->constrained('users');
            $t->text('body');
            $t->timestampTz('created_at')->useCurrent();
            $t->index('task_step_id');
        });

        Schema::create('task_step_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('task_step_id')->constrained('task_steps')->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users');
            $t->integer('submission_no')->default(1);            // which submission was judged
            $t->string('decision');                               // approved | changes_requested
            $t->text('comment')->nullable();
            $t->timestampTz('reviewed_at')->useCurrent();
            $t->index('task_step_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE task_step_reviews ADD CONSTRAINT tsr_decision_check
                CHECK (decision IN ('approved','changes_requested'))");
            // BRD §9.7 — requesting changes without a comment is not allowed anywhere.
            DB::statement("ALTER TABLE task_step_reviews ADD CONSTRAINT tsr_comment_required_check
                CHECK (decision <> 'changes_requested'
                       OR (comment IS NOT NULL AND length(trim(comment)) > 0))");
            DB::statement('ALTER TABLE task_step_outputs ADD CONSTRAINT tso_submission_check
                CHECK (submission_no > 0)');
            // An output cannot supersede itself.
            DB::statement('ALTER TABLE task_step_outputs ADD CONSTRAINT tso_no_self_supersede
                CHECK (superseded_by_output_id IS NULL OR superseded_by_output_id <> id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_step_reviews');
        Schema::dropIfExists('task_step_comments');
        Schema::table('task_step_outputs', function (Blueprint $t) {
            $t->dropForeign(['superseded_by_output_id']);
        });
        Schema::dropIfExists('task_step_outputs');
    }
};
