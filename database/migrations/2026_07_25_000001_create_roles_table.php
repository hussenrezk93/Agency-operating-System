<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ERD v1.2 · roles — verified: id, code (unique), name. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();   // admin / manager / tl / employee
            $t->string('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
