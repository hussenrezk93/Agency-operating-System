<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily Department Reports needs a stable way to single out three departments
 * (Content's extra report, Moderator as its recipient, Photography/Videography's
 * extended deadline) without matching on `name`, which is free-text and
 * Admin-editable (`DepartmentService::rename()`) — a rename would silently break a
 * name-matched rule. The backfill below is a one-time best-effort convenience for
 * environments that already have departments with these exact names; it is not
 * required for the feature to work — an Admin can always set special_role by hand
 * via the department edit form afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('special_role')->nullable()->after('name');
        });

        DB::table('departments')->where('name', 'content')->update(['special_role' => 'content']);
        DB::table('departments')->where('name', 'moderator')->update(['special_role' => 'moderator']);
        DB::table('departments')->where('name', 'photography/videography')->update(['special_role' => 'photography_videography']);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('special_role');
        });
    }
};
