<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Students and staff. `users` stays sign-in only; these hold the academic facts
// (one-to-one with a user), so a person can later be both without two accounts.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('staff_number', 30);
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'staff_number']);
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('index_number', 30);
            // Ministry of Health membership id: cross-reference only. Deliberately not unique and not a key (BE-009).
            $table->string('moh_membership_id', 30)->nullable()->index();
            $table->foreignId('programme_id')->constrained('programmes');
            $table->foreignId('cohort_id')->constrained('cohorts');
            $table->unsignedTinyInteger('year_of_study')->default(1);
            $table->string('status', 20)->default('active')->index();
            $table->unsignedTinyInteger('repeat_count')->default(0);
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'index_number']);
            $table->index(['programme_id', 'cohort_id']);
        });

        // One row per programme and year; locked while a number is issued, so two
        // people registered at the same moment can never receive the same number (BE-008).
        Schema::create('index_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('programme_id')->constrained('programmes');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_serial')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'programme_id', 'year']);
        });
    }

    public function down(): void
    {
        foreach (['index_number_sequences', 'student_profiles', 'staff_profiles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
