<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** ERD v1.2 · clients — verified: name, short_description null, phone NOT NULL (BRD §7.1),
 *  company_email null, website_url null, status, created_by, timestamps. Structural only. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('short_description')->nullable();
            $t->string('phone');
            $t->string('company_email')->nullable();
            $t->text('website_url')->nullable();
            $t->string('status')->default('active');
            $t->foreignId('created_by')->constrained('users');
            $t->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
