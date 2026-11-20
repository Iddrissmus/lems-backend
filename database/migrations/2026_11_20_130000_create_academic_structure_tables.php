<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Academic structure: who the college is made of. Every table carries a tenant_id
// (ADR 0010) and business-value uniques are per tenant. Design: docs/erd.md Part 2.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->string('code', 20);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('department_id')->constrained('departments');
            $table->string('name');
            $table->string('code', 20);
            $table->unsignedTinyInteger('duration_years')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name', 20); // 2026/2027
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('academic_year_id')->constrained('academic_years');
            $table->unsignedTinyInteger('number'); // 1 or 2
            // First-class and audited: billing will read it. A semester is exactly 16 weeks (contract).
            $table->date('starts_on');
            $table->unsignedTinyInteger('length_weeks')->default(16);
            $table->date('ends_on'); // derived from starts_on and length_weeks by the application
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['academic_year_id', 'number']);
        });

        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('programme_id')->constrained('programmes');
            $table->string('name');
            $table->unsignedSmallInteger('intake_year');
            $table->unsignedTinyInteger('current_year_of_study')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'programme_id', 'intake_year']);
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('department_id')->constrained('departments');
            $table->string('code', 20);
            $table->string('title');
            $table->decimal('credit_hours', 4, 1);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        // Which course belongs to which programme, in which year and semester, under
        // which curriculum version (courses are versioned across curriculum review, BE-016).
        Schema::create('programme_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('programme_id')->constrained('programmes');
            $table->foreignId('course_id')->constrained('courses');
            $table->unsignedTinyInteger('year_of_study');
            $table->unsignedTinyInteger('semester_number');
            $table->string('curriculum_version', 20)->default('v1');
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->unique(['programme_id', 'course_id', 'curriculum_version'], 'programme_courses_curriculum_unique');
            $table->index(['programme_id', 'year_of_study', 'semester_number'], 'programme_courses_lookup_index');
        });
    }

    public function down(): void
    {
        foreach (['programme_courses', 'courses', 'cohorts', 'semesters', 'academic_years', 'programmes', 'departments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
