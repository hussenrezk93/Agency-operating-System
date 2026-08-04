<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERD v1.2 · projects + project_departments — STRUCTURAL SKELETON ONLY.
 * Verified fields: client_id (one client per project), project_code unique (Q21 format
 * pending — column is format-agnostic), name, description, status, created_by,
 * started_at (= creation, BRD §7.2), completed/cancelled metadata columns.
 * ERD WhatsApp columns on projects (whatsapp_group_url/label/version/updated_*) are
 * confirmed schema and included INERT — the versioning/delivery LEDGER TABLES and all
 * behavior are deferred with the WhatsApp module (out of scope today, Q23).
 * project_links (ERD) deferred to the same module batch. NO workflow actions implemented.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('client_id')->constrained('clients');
            $t->string('project_code')->unique();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('status')->default('active');
            $t->foreignId('created_by')->constrained('users');
            $t->timestampTz('started_at')->useCurrent();
            $t->timestampTz('completed_at')->nullable();
            $t->foreignId('completed_by')->nullable()->constrained('users');
            $t->timestampTz('cancelled_at')->nullable();
            $t->foreignId('cancelled_by')->nullable()->constrained('users');
            $t->text('cancelled_reason')->nullable();
            $t->text('whatsapp_group_url')->nullable();
            $t->string('whatsapp_group_label')->nullable();
            $t->integer('whatsapp_link_version')->default(0);
            $t->timestampTz('whatsapp_link_updated_at')->nullable();
            $t->foreignId('whatsapp_link_updated_by')->nullable()->constrained('users');
        });

        Schema::create('project_departments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects');
            $t->foreignId('department_id')->constrained('departments');
            $t->boolean('is_active')->default(true);
            $t->timestampTz('added_at')->useCurrent();
            $t->timestampTz('removed_at')->nullable();
            $t->unique(['project_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_departments');
        Schema::dropIfExists('projects');
    }
};
