<?php

use App\Domain\Academics\Actions\IssueIndexNumber;
use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrolment;
use App\Models\GradeScale;
use App\Models\GradeScaleBand;
use App\Models\IndexNumberSequence;
use App\Models\LecturerAssignment;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// The academic model (docs/erd.md Part 2): every table belongs to a tenant, the
// rules the schema promises actually hold, and index numbers never collide.

/** @var array<int, class-string<Model>> $academicModels */
$academicModels = [
    Department::class, Programme::class, AcademicYear::class, Semester::class, Cohort::class,
    Course::class, ProgrammeCourse::class, CourseOffering::class, StaffProfile::class,
    StudentProfile::class, IndexNumberSequence::class, LecturerAssignment::class,
    RegistrationWindow::class, Enrolment::class, GradeScale::class, GradeScaleBand::class,
    Assessment::class, AssessmentScore::class,
];

describe('tenancy covers every table', function () use ($academicModels) {
    it('has a tenant-scoped model for every table that has a tenant_id', function () {
        $tables = collect(DB::select("SELECT DISTINCT TABLE_NAME AS name FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id'"))
            ->pluck('name')->sort()->values()->all();

        $modelTables = collect(glob(app_path('Models/*.php')))
            ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
            ->filter(fn (string $class) => in_array(BelongsToTenant::class, class_uses_recursive($class), true))
            ->map(fn (string $class) => (new $class)->getTable())
            ->sort()->values()->all();

        // A table with a tenant_id but no scoped model would leak between colleges.
        expect($modelTables)->toBe($tables);
    });

    it('isolates every academic model between colleges', function (string $class) {
        config(['tenancy.enabled' => true]);
        $a = newTenant('college-a');
        $b = newTenant('college-b');

        $made = tenancyContext()->runAs($a, fn () => $class::factory()->create());

        expect(tenancyContext()->runAs($a, fn () => $class::query()->whereKey($made->getKey())->exists()))->toBeTrue()
            ->and(tenancyContext()->runAs($b, fn () => $class::query()->whereKey($made->getKey())->exists()))->toBeFalse()
            ->and(tenancyContext()->runAs($b, fn () => $class::query()->count()))->toBe(0)
            ->and($made->tenant_id)->toBe($a->id);
    })->with($academicModels);
});

describe('the chain works end to end', function () {
    it('registers a student and records a score through the relations', function () {
        $department = Department::factory()->create();
        $programme = Programme::factory()->create(['department_id' => $department->id]);
        $student = StudentProfile::factory()->create(['programme_id' => $programme->id]);
        $offering = CourseOffering::factory()->create();
        $enrolment = Enrolment::factory()->create(['student_profile_id' => $student->id, 'course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create(['course_offering_id' => $offering->id]);
        $score = AssessmentScore::factory()->create(['assessment_id' => $assessment->id, 'enrolment_id' => $enrolment->id]);

        expect($student->programme->is($programme))->toBeTrue()
            ->and($student->cohort->programme_id)->toBe($programme->id)
            ->and($student->status)->toBe(StudentStatus::Active)
            ->and($enrolment->status)->toBe(EnrolmentStatus::Registered)
            ->and($enrolment->student->is($student))->toBeTrue()
            ->and($enrolment->scores->pluck('id')->all())->toBe([$score->id])
            ->and($offering->enrolments->pluck('id')->all())->toBe([$enrolment->id])
            ->and($score->assessment->offering->is($offering))->toBeTrue()
            ->and(strlen($student->ulid))->toBe(26);
    });

    it('links a lecturer to an offering', function () {
        $assignment = LecturerAssignment::factory()->create();

        expect($assignment->offering->lecturerAssignments->pluck('id')->all())->toBe([$assignment->id])
            ->and($assignment->staff)->toBeInstanceOf(StaffProfile::class);
    });
});

describe('the rules the schema promises', function () {
    it('keeps business codes unique within a college, but not across colleges', function () {
        Department::factory()->create(['code' => 'NUR']);

        expect(fn () => Department::factory()->create(['code' => 'NUR']))->toThrow(UniqueConstraintViolationException::class);

        $other = newTenant('other-college');
        $copy = tenancyContext()->runAs($other, fn () => Department::factory()->create(['code' => 'NUR']));

        expect($copy->tenant_id)->toBe($other->id);
    });

    it('allows one course per programme and curriculum version only once', function () {
        $link = ProgrammeCourse::factory()->create();

        expect(fn () => ProgrammeCourse::factory()->create(['programme_id' => $link->programme_id, 'course_id' => $link->course_id]))
            ->toThrow(UniqueConstraintViolationException::class);

        $v2 = ProgrammeCourse::factory()->create(['programme_id' => $link->programme_id, 'course_id' => $link->course_id, 'curriculum_version' => 'v2']);

        expect($v2->curriculum_version)->toBe('v2');
    });

    it('offers a course once per semester', function () {
        $offering = CourseOffering::factory()->create();

        expect(fn () => CourseOffering::factory()->create(['course_id' => $offering->course_id, 'semester_id' => $offering->semester_id]))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('registers a student for an offering only once', function () {
        $enrolment = Enrolment::factory()->create();

        expect(fn () => Enrolment::factory()->create(['student_profile_id' => $enrolment->student_profile_id, 'course_offering_id' => $enrolment->course_offering_id]))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('does not treat the Ministry of Health membership id as a key', function () {
        StudentProfile::factory()->create(['moh_membership_id' => 'MOH-1']);
        $second = StudentProfile::factory()->create(['moh_membership_id' => 'MOH-1']);

        expect($second->moh_membership_id)->toBe('MOH-1');
    });

    it('keeps index numbers unique within a college', function () {
        StudentProfile::factory()->create(['index_number' => 'RGN/2026/0001']);

        expect(fn () => StudentProfile::factory()->create(['index_number' => 'RGN/2026/0001']))->toThrow(UniqueConstraintViolationException::class);
    });

    it('stores a 16 week semester and lets a grade scale hold bands', function () {
        $semester = Semester::factory()->create();
        $scale = GradeScale::factory()->create();
        GradeScaleBand::factory()->create(['grade_scale_id' => $scale->id, 'letter' => 'B', 'min_score' => 70, 'max_score' => 79.99]);

        expect($semester->length_weeks)->toBe(16)
            ->and($semester->starts_on->addWeeks(16)->subDay()->toDateString())->toBe($semester->ends_on->toDateString())
            ->and($scale->bands)->toHaveCount(1);
    });

    it('stores the audit reason that grade changes need', function () {
        $entry = AuditLog::create(['event' => 'user.updated', 'reason' => 'Corrected a transcription error']);

        expect($entry->fresh()->reason)->toBe('Corrected a transcription error');
    });
});

describe('index numbers', function () {
    it('counts up per programme and year, and never repeats', function () {
        $rgn = Programme::factory()->create(['code' => 'RGN']);
        $rm = Programme::factory()->create(['code' => 'RM']);
        $issue = app(IssueIndexNumber::class);

        expect($issue->execute($rgn, 2026))->toBe('RGN/2026/0001')
            ->and($issue->execute($rgn, 2026))->toBe('RGN/2026/0002')
            ->and($issue->execute($rgn, 2027))->toBe('RGN/2027/0001')
            ->and($issue->execute($rm, 2026))->toBe('RM/2026/0001')
            ->and($issue->execute($rgn, 2026))->toBe('RGN/2026/0003');
    });

    it('continues from the stored sequence', function () {
        $programme = Programme::factory()->create(['code' => 'RGN']);
        IndexNumberSequence::factory()->create(['programme_id' => $programme->id, 'year' => 2026, 'last_serial' => 41]);

        expect(app(IssueIndexNumber::class)->execute($programme, 2026))->toBe('RGN/2026/0042');
    });

    it('issues many numbers without a duplicate', function () {
        $programme = Programme::factory()->create(['code' => 'RGN']);
        $issue = app(IssueIndexNumber::class);

        $numbers = collect(range(1, 25))->map(fn () => $issue->execute($programme, 2026));

        expect($numbers->unique())->toHaveCount(25);
    });
});
