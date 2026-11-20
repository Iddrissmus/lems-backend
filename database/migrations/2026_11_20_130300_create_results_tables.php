<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Results: the grade scale (configuration), what is assessed, and the scores.
// Scores lock when submitted, enforced by the database itself (BE-023): a trigger
// refuses any later UPDATE or DELETE of a submitted row. A correction is a NEW row
// with a higher version, never an edit (BE-025, BE-026).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');
            $table->boolean('is_active')->default(false);
            // True for the synthetic scale used in demos: not the College's rules (D5).
            $table->boolean('is_demo')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('grade_scale_bands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('grade_scale_id')->constrained('grade_scales');
            $table->string('letter', 3);
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            // GPA points stay empty until the College confirms its metric (D5).
            $table->decimal('points', 3, 2)->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['grade_scale_id', 'letter']);
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('course_offering_id')->constrained('course_offerings');
            $table->string('name'); // "Overall" for the demo
            $table->decimal('weight_percent', 5, 2)->default(100);
            $table->decimal('max_score', 6, 2)->default(100);
            $table->timestamps();

            $table->unique(['course_offering_id', 'name']);
        });

        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('assessment_id')->constrained('assessments');
            $table->foreignId('enrolment_id')->constrained('enrolments');
            $table->unsignedSmallInteger('version')->default(1); // 1, then 2, 3... for each correction
            $table->decimal('score', 6, 2); // never shown to students (BE-027)
            // Stored when submitted, with the scale used, so a later scale change cannot rewrite published results.
            $table->string('letter_grade', 3)->nullable();
            $table->foreignId('grade_scale_id')->nullable()->constrained('grade_scales');
            $table->string('status', 20)->default('draft'); // draft, then submitted (locked)
            $table->foreignId('entered_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('supersedes_id')->nullable()->constrained('assessment_scores');
            $table->text('reason')->nullable(); // required for version 2 and up
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['assessment_id', 'enrolment_id', 'version'], 'assessment_scores_version_unique');
            $table->index('enrolment_id');
        });

        // The lock itself. Draft rows may change (including becoming submitted);
        // once a row is submitted nothing can update or delete it.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER assessment_scores_locked_update BEFORE UPDATE ON assessment_scores
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'submitted' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'grade_locked';
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER assessment_scores_locked_delete BEFORE DELETE ON assessment_scores
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'submitted' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'grade_locked';
                END IF;
            END
        SQL);

        // Grade changes must say why (BE-026). Stored with the audit entry.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('reason')->nullable()->after('after');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('reason');
        });

        DB::unprepared('DROP TRIGGER IF EXISTS assessment_scores_locked_update');
        DB::unprepared('DROP TRIGGER IF EXISTS assessment_scores_locked_delete');

        foreach (['assessment_scores', 'assessments', 'grade_scale_bands', 'grade_scales'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
