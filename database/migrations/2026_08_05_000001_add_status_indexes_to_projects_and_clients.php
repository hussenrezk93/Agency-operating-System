<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance-optimization pass — `projects.status` and `clients.status` are filtered on
 * every dashboard count/list (`Project::where('status', 'active')`,
 * `Client::where('status', 'active')`) but never had their own index, unlike every other
 * status-style column in the schema (`tasks.lifecycle_status`, `task_steps.workflow_status`,
 * `users.status`, ...). Purely additive — no behavior change, same results, faster scans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->index('status');
        });

        Schema::table('clients', function (Blueprint $t) {
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->dropIndex(['status']);
        });

        Schema::table('clients', function (Blueprint $t) {
            $t->dropIndex(['status']);
        });
    }
};
