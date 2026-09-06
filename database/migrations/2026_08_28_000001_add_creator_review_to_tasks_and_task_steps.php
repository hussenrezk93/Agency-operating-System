<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('creator_review_required')->default(false)->after('created_by');
        });

        Schema::table('task_steps', function (Blueprint $table) {
            $table->timestampTz('creator_reviewed_at')->nullable()->after('approved_at');
            $table->text('creator_review_comment')->nullable()->after('creator_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('task_steps', function (Blueprint $table) {
            $table->dropColumn(['creator_reviewed_at', 'creator_review_comment']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('creator_review_required');
        });
    }
};
