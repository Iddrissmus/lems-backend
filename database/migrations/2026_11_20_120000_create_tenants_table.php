<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // public id
            $table->string('name');
            $table->string('slug', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        // The one tenant that exists while tenancy is switched off. Created here,
        // not in a seeder, so every environment (and every test database) has it.
        DB::table('tenants')->insert([
            'ulid' => Str::lower((string) Str::ulid()),
            'name' => 'Savior Church Nursing & Midwifery Training College',
            'slug' => 'scnmtc',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
