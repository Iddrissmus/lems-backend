<?php

use App\Domain\Identity\Enums\Role;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use App\Models\Semester;

// Reference data fills the registration screens' filters (docs/api/demo-contract.md section 3).

beforeEach(function () {
    $this->nursing = Department::factory()->create(['code' => 'NUR', 'name' => 'Nursing']);
    $this->midwifery = Department::factory()->create(['code' => 'MID', 'name' => 'Midwifery']);
    $this->rgn = Programme::factory()->create(['department_id' => $this->nursing->id, 'code' => 'RGN', 'name' => 'Registered General Nursing']);
    $this->rm = Programme::factory()->create(['department_id' => $this->midwifery->id, 'code' => 'RM', 'name' => 'Registered Midwifery']);
    Cohort::factory()->create(['programme_id' => $this->rgn->id, 'intake_year' => 2026, 'name' => 'RGN class of 2029']);
    Cohort::factory()->create(['programme_id' => $this->rm->id, 'intake_year' => 2025, 'name' => 'RM class of 2028']);

    $this->current = Semester::factory()->create(['number' => 1, 'is_current' => true]);
    $this->other = Semester::factory()->create(['academic_year_id' => $this->current->academic_year_id, 'number' => 2, 'starts_on' => '2027-01-18', 'ends_on' => '2027-05-09']);

    $make = function (string $code, string $title, Department $dept, Programme $programme, int $year, Semester $semester, float $credits = 3) {
        $course = Course::factory()->create(['code' => $code, 'title' => $title, 'department_id' => $dept->id, 'credit_hours' => $credits]);
        ProgrammeCourse::factory()->create(['programme_id' => $programme->id, 'course_id' => $course->id, 'year_of_study' => $year, 'semester_number' => $semester->number]);

        return CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'exam_date' => '2026-12-14']);
    };

    $this->nur111 = $make('NUR 111', 'Foundations of Nursing', $this->nursing, $this->rgn, 1, $this->current);
    $this->nur211 = $make('NUR 211', 'Medical-Surgical Nursing I', $this->nursing, $this->rgn, 2, $this->current, 4);
    $this->mid111 = $make('MID 111', 'Foundations of Midwifery', $this->midwifery, $this->rm, 1, $this->current);
    $this->nur113 = $make('NUR 113', 'Communication Skills', $this->nursing, $this->rgn, 1, $this->other, 2);

    // A course serving two programmes
    $shared = Course::factory()->create(['code' => 'SDV 101', 'title' => 'Study Skills', 'department_id' => $this->nursing->id, 'credit_hours' => 2]);
    ProgrammeCourse::factory()->create(['programme_id' => $this->rgn->id, 'course_id' => $shared->id, 'year_of_study' => 1, 'semester_number' => 1]);
    ProgrammeCourse::factory()->create(['programme_id' => $this->rm->id, 'course_id' => $shared->id, 'year_of_study' => 1, 'semester_number' => 1]);
    $this->sdv101 = CourseOffering::factory()->create(['course_id' => $shared->id, 'semester_id' => $this->current->id]);
});

describe('who may read reference data', function () {
    it('lets people who work with students or registration read it', function (Role $role) {
        actingAsRole($role);

        foreach (['departments', 'programmes', 'cohorts', 'semesters', 'course-offerings'] as $path) {
            $this->getJson("/api/v1/{$path}")->assertOk();
        }
    })->with([Role::SuperAdmin, Role::HeadOfAcademics, Role::IctAdmin, Role::Registrar, Role::AcademicAffairs, Role::Student]);

    it('keeps it from roles with no student or registration permission', function (Role $role) {
        actingAsRole($role);

        $this->getJson('/api/v1/departments')->assertForbidden()->assertJsonPath('code', 'forbidden');
        $this->getJson('/api/v1/course-offerings')->assertForbidden();
    })->with([Role::Lecturer, Role::Bursar]);

    it('needs a token', function () {
        $this->getJson('/api/v1/departments')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });
});

describe('the lists', function () {
    beforeEach(fn () => actingAsRole(Role::Registrar));

    it('lists departments by name', function () {
        $this->getJson('/api/v1/departments')->assertOk()
            ->assertJsonPath('data.0', ['id' => $this->midwifery->ulid, 'code' => 'MID', 'name' => 'Midwifery'])
            ->assertJsonCount(2, 'data');
    });

    it('lists programmes with their department and filters by department', function () {
        $this->getJson('/api/v1/programmes')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/programmes?department={$this->nursing->ulid}")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'RGN')
            ->assertJsonPath('data.0.duration_years', 3)
            ->assertJsonPath('data.0.department.id', $this->nursing->ulid);
    });

    it('lists cohorts, newest intake first, and filters by programme', function () {
        $this->getJson('/api/v1/cohorts')->assertOk()->assertJsonPath('data.0.intake_year', 2026);

        $this->getJson("/api/v1/cohorts?programme={$this->rm->ulid}")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.programme_id', $this->rm->ulid);
    });

    it('lists semesters and finds the current one', function () {
        $this->getJson('/api/v1/semesters')->assertOk()->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/semesters?current=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->current->ulid)
            ->assertJsonPath('data.0.length_weeks', 16)
            ->assertJsonPath('data.0.is_current', true);
    });
});

describe('courses on offer', function () {
    beforeEach(fn () => actingAsRole(Role::Student));

    it('shows the current semester by default, ordered by course code', function () {
        $codes = collect($this->getJson('/api/v1/course-offerings')->assertOk()->json('data'))->pluck('course.code')->all();

        expect($codes)->toBe(['MID 111', 'NUR 111', 'NUR 211', 'SDV 101']);
    });

    it('has the shape the registration screen needs', function () {
        $this->getJson('/api/v1/course-offerings?q=NUR 211')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->nur211->ulid)
            ->assertJsonPath('data.0.course.title', 'Medical-Surgical Nursing I')
            ->assertJsonPath('data.0.course.credit_hours', 4)
            ->assertJsonPath('data.0.course.department.code', 'NUR')
            ->assertJsonPath('data.0.semester.number', 1)
            ->assertJsonPath('data.0.classes', [['programme' => ['id' => $this->rgn->ulid, 'code' => 'RGN', 'name' => 'Registered General Nursing'], 'year_of_study' => 2]])
            ->assertJsonPath('data.0.exam_date', '2026-12-14');
    });

    it('lists every class a shared course is for', function () {
        $classes = $this->getJson('/api/v1/course-offerings?q=Study')->assertOk()->json('data.0.classes');

        expect(collect($classes)->pluck('programme.code')->sort()->values()->all())->toBe(['RGN', 'RM']);
    });

    it('filters by programme and year together, on the same curriculum link', function () {
        $codes = fn (string $query) => collect($this->getJson("/api/v1/course-offerings?{$query}")->assertOk()->json('data'))->pluck('course.code')->sort()->values()->all();

        expect($codes("programme={$this->rgn->ulid}&year=1"))->toBe(['NUR 111', 'SDV 101'])
            ->and($codes("programme={$this->rgn->ulid}&year=2"))->toBe(['NUR 211'])
            ->and($codes("programme={$this->rm->ulid}&year=2"))->toBe([])
            ->and($codes("department={$this->midwifery->ulid}"))->toBe(['MID 111'])
            ->and($codes('q=Foundations'))->toBe(['MID 111', 'NUR 111']);
    });

    it('shows another semester only when asked for it', function () {
        $codes = collect($this->getJson("/api/v1/course-offerings?semester={$this->other->ulid}")->assertOk()->json('data'))->pluck('course.code')->all();

        expect($codes)->toBe(['NUR 113']);
    });

    it('returns nothing for an unknown id instead of everything', function () {
        $this->getJson('/api/v1/course-offerings?semester=01jd2k9m4q7x3v8n5r6t1y0zzz')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/course-offerings?programme=01jd2k9m4q7x3v8n5r6t1y0zzz')->assertOk()->assertJsonCount(0, 'data');
    });

    it('pages the list and refuses bad filters', function () {
        $this->getJson('/api/v1/course-offerings?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 2);

        foreach (['per_page=1000', 'year=0', 'year=abc', 'semester=short'] as $bad) {
            $this->getJson("/api/v1/course-offerings?{$bad}")->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        }
    });
});

describe('between colleges', function () {
    it('shows each college only its own reference data', function () {
        config(['tenancy.enabled' => true]);
        $a = newTenant('college-a');
        $b = newTenant('college-b');

        $adminA = tenancyContext()->runAs($a, function () {
            Department::factory()->create(['code' => 'AAA', 'name' => 'Only A']);

            return userWithRole(Role::Registrar);
        });
        tenancyContext()->runAs($b, fn () => Department::factory()->create(['code' => 'BBB', 'name' => 'Only B']));
        $token = $adminA->createToken('t')->plainTextToken;

        $codes = collect(apiAs($token)->getJson('/api/v1/departments')->assertOk()->json('data'))->pluck('code')->all();

        expect($codes)->toBe(['AAA']);
    });
});
