<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ERD v1.2 · project_links — general project reference links (BRD §7.2).
 *  Links only: Agency OS stores no files in v1. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects');
            $t->foreignId('added_by')->constrained('users');
            $t->text('url');
            $t->string('label')->nullable();
            $t->timestampTz('created_at')->useCurrent();
            $t->index('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_links');
    }
};
