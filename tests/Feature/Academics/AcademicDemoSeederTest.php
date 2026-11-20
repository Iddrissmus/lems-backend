<?php

use App\Models\CourseOffering;
use App\Models\Enrolment;
use App\Models\GradeScale;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\AcademicDemoSeeder;
use Illuminate\Support\Facades\DB;

// The synthetic demo data (docs/demo-data.md): realistic enough to demonstrate the
// flow, invented, local only, and safe to run again.

function academicCounts(): array
{
    return collect(['departments', 'programmes', 'academic_years', 'semesters', 'cohorts', 'courses', 'programme_courses', 'course_offerings', 'staff_profiles', 'student_profiles', 'index_number_sequences', 'lecturer_assignments', 'assessments', 'enrolments', 'grade_scales', 'grade_scale_bands', 'registration_windows', 'users'])
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])
        ->all();
}

it('refuses to run outside the local environment', function () {
    app()['env'] = 'staging';

    expect(fn () => $this->seed(AcademicDemoSeeder::class))->toThrow(RuntimeException::class, 'local environment only');
});

describe('once seeded', function () {
    beforeEach(function () {
        app()['env'] = 'local';
        $this->seed(AcademicDemoSeeder::class);
    });

    it('has the sizes the demo promises', function () {
        $counts = academicCounts();

        expect($counts['departments'])->toBe(3)
            ->and($counts['programmes'])->toBe(2)
            ->and($counts['semesters'])->toBe(2)
            ->and($counts['cohorts'])->toBe(6)
            ->and($counts['courses'])->toBe(12)
            ->and($counts['course_offerings'])->toBe(9)
            ->and($counts['student_profiles'])->toBeBetween(38, 44)
            ->and($counts['staff_profiles'])->toBe(9)
            ->and($counts['grade_scale_bands'])->toBe(5);
    });

    it('has two sixteen week semesters, the first current', function () {
        $semesters = Semester::orderBy('number')->get();

        expect($semesters)->toHaveCount(2)
            ->and($semesters->every(fn (Semester $s) => $s->length_weeks === 16 && $s->starts_on->addWeeks(16)->subDay()->equalTo($s->ends_on)))->toBeTrue()
            ->and($semesters->pluck('is_current')->all())->toBe([true, false]);
    });

    it('gives every offering a lecturer and an assessment', function () {
        expect(CourseOffering::whereDoesntHave('lecturerAssignments')->count())->toBe(0)
            ->and(CourseOffering::whereDoesntHave('assessments')->count())->toBe(0);
    });

    it('puts the demo lecturer in charge of at least two courses', function () {
        $lecturer = User::where('email', 'lecturer@demo.test')->firstOrFail();

        expect($lecturer->hasRole('lecturer'))->toBeTrue()
            ->and(CourseOffering::whereHas('lecturerAssignments.staff', fn ($q) => $q->where('user_id', $lecturer->id))->count())->toBeGreaterThanOrEqual(2);
    });

    it('enrols students only in courses of their own programme and year', function () {
        $wrong = Enrolment::with(['student', 'offering.course.programmeCourses'])->get()->filter(function (Enrolment $e) {
            return ! $e->offering->course->programmeCourses->contains(
                fn ($link) => $link->programme_id === $e->student->programme_id && $link->year_of_study === $e->student->year_of_study && $link->semester_number === 1,
            );
        });

        expect(Enrolment::count())->toBeGreaterThan(30)
            ->and($wrong)->toHaveCount(0);
    });

    it('leaves the demo student with nothing registered, so registering can be shown live', function () {
        $demo = StudentProfile::whereHas('user', fn ($q) => $q->where('email', 'student@demo.test'))->firstOrFail();

        expect($demo->enrolments)->toHaveCount(0)
            ->and($demo->programme->code)->toBe('RGN')
            ->and($demo->year_of_study)->toBe(1)
            ->and($demo->user->hasRole('student'))->toBeTrue();
    });

    it('has an open registration window and a synthetic grade scale marked as demo', function () {
        $window = RegistrationWindow::firstOrFail();
        $scale = GradeScale::firstOrFail();

        expect(now()->between($window->opens_at, $window->closes_at))->toBeTrue()
            ->and($scale->is_demo)->toBeTrue()
            ->and($scale->is_active)->toBeTrue()
            ->and($scale->bands->pluck('points')->filter()->all())->toBe([]);
    });

    it('gives every student a unique index number and demo accounts only use @demo.test', function () {
        $numbers = StudentProfile::pluck('index_number');

        expect($numbers->unique())->toHaveCount($numbers->count())
            ->and(User::where('email', 'not like', '%@demo.test')->count())->toBe(0);
    });

    it('creates nothing new when run again', function () {
        $before = academicCounts();

        $this->seed(AcademicDemoSeeder::class);

        expect(academicCounts())->toBe($before);
    });
});
