<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Teaching: a course delivered in a semester (offering), who teaches it, when
// students may register, and who registered.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offerings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('semester_id')->constrained('semesters');
            $table->date('exam_date')->nullable();
            // Set when the lecturer submits the course: its scores lock.
            $table->timestamp('marks_submitted_at')->nullable();
            $table->foreignId('marks_submitted_by')->nullable()->constrained('users');
            // Set by the Head of Academics: only then can students see the results.
            $table->timestamp('results_released_at')->nullable();
            $table->foreignId('results_released_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['course_id', 'semester_id']);
        });

        Schema::create('lecturer_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('course_offering_id')->constrained('course_offerings');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->timestamps();

            $table->unique(['course_offering_id', 'staff_profile_id'], 'lecturer_assignments_pair_unique');
        });

        // When students may register. The audience says who (programmes and years); empty means everyone.
        Schema::create('registration_windows', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('semester_id')->constrained('semesters');
            // dateTime, not timestamp: MariaDB gives NOT NULL timestamps odd defaults and auto-updates.
            $table->dateTime('opens_at');
            $table->dateTime('closes_at');
            $table->json('audience')->nullable();
            $table->timestamp('closed_at')->nullable(); // closed early by staff
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['semester_id', 'opens_at', 'closes_at']);
        });

        Schema::create('enrolments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('student_profile_id')->constrained('student_profiles');
            $table->foreignId('course_offering_id')->constrained('course_offerings');
            $table->string('status', 20)->default('registered'); // registered or withdrawn
            $table->dateTime('registered_at');
            $table->timestamps();

            $table->unique(['student_profile_id', 'course_offering_id']);
            $table->index(['course_offering_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['enrolments', 'registration_windows', 'lecturer_assignments', 'course_offerings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
