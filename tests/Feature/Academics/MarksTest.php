<?php

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Identity\Enums\Role;
use App\Models\AssessmentScore;
use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrolment;
use App\Models\GradeScale;
use App\Models\GradeScaleBand;
use App\Models\LecturerAssignment;
use App\Models\Programme;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// Lecturer marks, the Head of Academics' release and the student's results
// (docs/api/demo-contract.md sections 7 to 9).

/** Signs in as this user for the next calls, forgetting whoever was signed in before. */
function signInAs(User $user): void
{
    app('auth')->forgetGuards();
    Sanctum::actingAs($user);
}

/** @param  array<string, float|int|null>  $scores  index number => score */
function putMarks(CourseOffering $offering, array $scores)
{
    $rows = [];
    foreach ($scores as $index => $score) {
        $enrolment = Enrolment::query()->where('course_offering_id', $offering->id)
            ->whereHas('student', fn ($s) => $s->where('index_number', $index))->firstOrFail();
        $rows[] = ['enrolment_id' => $enrolment->ulid, 'score' => $score];
    }

    return test()->putJson("/api/v1/lecturer/course-offerings/{$offering->ulid}/marks", ['marks' => $rows]);
}

beforeEach(function () {
    $scale = GradeScale::factory()->create(['name' => 'Demo scale (synthetic, not the College rules)', 'is_active' => true, 'is_demo' => true]);
    foreach ([['A', 80, 100, 4.0], ['B', 70, 79.99, 3.0], ['C', 60, 69.99, 2.0], ['D', 50, 59.99, 1.0], ['F', 0, 49.99, 0.0]] as $sort => [$letter, $min, $max, $points]) {
        GradeScaleBand::factory()->create(['grade_scale_id' => $scale->id, 'letter' => $letter, 'min_score' => $min, 'max_score' => $max, 'points' => $points, 'sort' => $sort + 1]);
    }

    $department = Department::factory()->create();
    $this->programme = Programme::factory()->create(['department_id' => $department->id, 'code' => 'RGN']);
    $this->cohort = Cohort::factory()->create(['programme_id' => $this->programme->id]);
    $this->semester = Semester::factory()->create(['is_current' => true]);

    $this->offer = function (string $code, float $credits = 3): CourseOffering {
        $course = Course::factory()->create(['code' => $code, 'title' => "Title of {$code}", 'credit_hours' => $credits]);

        return CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $this->semester->id]);
    };

    $this->nur111 = ($this->offer)('NUR 111');
    $this->nur112 = ($this->offer)('NUR 112', 4);

    $this->lecturer = userWithRole(Role::Lecturer, ['name' => 'Lecturer One']);
    $this->staff = StaffProfile::factory()->create(['user_id' => $this->lecturer->id, 'department_id' => $department->id]);
    LecturerAssignment::factory()->create(['course_offering_id' => $this->nur111->id, 'staff_profile_id' => $this->staff->id]);

    $this->otherLecturer = userWithRole(Role::Lecturer);
    $otherStaff = StaffProfile::factory()->create(['user_id' => $this->otherLecturer->id]);
    LecturerAssignment::factory()->create(['course_offering_id' => $this->nur112->id, 'staff_profile_id' => $otherStaff->id]);

    $this->enrol = function (string $index, string $name, CourseOffering $offering, EnrolmentStatus $status = EnrolmentStatus::Registered): Enrolment {
        $student = StudentProfile::query()->where('index_number', $index)->first()
            ?? StudentProfile::factory()->create([
                'user_id' => userWithRole(Role::Student, ['name' => $name])->id,
                'programme_id' => $this->programme->id, 'cohort_id' => $this->cohort->id, 'index_number' => $index,
            ]);

        return Enrolment::factory()->create(['student_profile_id' => $student->id, 'course_offering_id' => $offering->id, 'status' => $status]);
    };

    $this->kofi = ($this->enrol)('RGN/2026/0001', 'Kofi Owusu', $this->nur111);
    $this->esi = ($this->enrol)('RGN/2026/0002', 'Esi Darko', $this->nur111);
    $this->yaw = ($this->enrol)('RGN/2026/0003', 'Yaw Boateng', $this->nur111);
    ($this->enrol)('RGN/2026/0004', 'Ama Withdrawn', $this->nur111, EnrolmentStatus::Withdrawn);
    ($this->enrol)('RGN/2026/0001', 'Kofi Owusu', $this->nur112); // Kofi also takes the other course

    $this->head = userWithRole(Role::HeadOfAcademics);
});

describe('my courses (lecturer)', function () {
    it('lists only the courses I am assigned to, with counts and status', function () {
        signInAs($this->lecturer);

        $response = $this->getJson('/api/v1/lecturer/course-offerings')->assertOk();

        expect($response->json('data'))->toHaveCount(1);
        $response->assertJsonPath('data.0.id', $this->nur111->ulid)
            ->assertJsonPath('data.0.course.code', 'NUR 111')
            ->assertJsonPath('data.0.course.credit_hours', 3)
            ->assertJsonPath('data.0.enrolled_count', 3)
            ->assertJsonPath('data.0.marks_entered_count', 0)
            ->assertJsonPath('data.0.status', 'not_started')
            ->assertJsonPath('data.0.submitted_at', null);
    });

    it('moves from not_started to draft when a mark is saved', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72.5])->assertOk();

        $this->getJson('/api/v1/lecturer/course-offerings')
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.marks_entered_count', 1);
    });

    it('is closed to roles without courses:teach', function (Role $role) {
        actingAsRole($role);

        $this->getJson('/api/v1/lecturer/course-offerings')->assertForbidden()->assertJsonPath('code', 'forbidden');
    })->with([Role::HeadOfAcademics, Role::Student, Role::Registrar, Role::Bursar]);

    it('requires a token', function () {
        $this->getJson('/api/v1/lecturer/course-offerings')->assertUnauthorized();
    });
});

describe('the class list and review', function () {
    it('shows the registered students in index number order, without withdrawn ones', function () {
        signInAs($this->lecturer);

        $response = $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks")->assertOk();

        expect(collect($response->json('data.students'))->pluck('student.index_number')->all())
            ->toBe(['RGN/2026/0001', 'RGN/2026/0002', 'RGN/2026/0003']);

        $response->assertJsonPath('data.offering.course.code', 'NUR 111')
            ->assertJsonPath('data.assessment.name', 'Overall')
            ->assertJsonPath('data.assessment.max_score', 100)
            ->assertJsonPath('data.grading.is_demo', true)
            ->assertJsonPath('data.students.0.state', 'empty')
            ->assertJsonPath('data.students.0.score', null)
            ->assertJsonPath('data.summary', ['enrolled' => 3, 'entered' => 0, 'missing' => 3]);
    });

    it('previews the letter grade of draft marks from the active scale', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72.5, 'RGN/2026/0002' => 85, 'RGN/2026/0003' => 49.99])->assertOk();

        $students = $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks")->json('data.students');

        expect(collect($students)->pluck('letter_grade')->all())->toBe(['B', 'A', 'F'])
            ->and(collect($students)->pluck('state')->unique()->all())->toBe(['draft'])
            ->and($students[0]['score'])->toBe(72.5);
    });

    it('answers 404 for a course that is not mine, and 403 without the permission', function () {
        signInAs($this->lecturer);
        $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur112->ulid}/marks")->assertNotFound()->assertJsonPath('code', 'not_found');

        signInAs($this->head);
        $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks")->assertForbidden();
    });

    it('does not let even super_admin use the lecturer routes for a course they do not teach', function () {
        signInAs(userWithRole(Role::SuperAdmin));

        $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks")->assertNotFound();
    });

    it('never accepts a numeric id', function () {
        signInAs($this->lecturer);

        $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur111->id}/marks")->assertNotFound();
    });
});

describe('saving draft marks', function () {
    it('saves, changes and clears marks', function () {
        signInAs($this->lecturer);

        putMarks($this->nur111, ['RGN/2026/0001' => 60, 'RGN/2026/0002' => 71])->assertOk()
            ->assertJsonPath('data.summary.entered', 2)
            ->assertJsonPath('data.students.0.letter_grade', 'C');

        putMarks($this->nur111, ['RGN/2026/0001' => 91.25, 'RGN/2026/0002' => null])->assertOk()
            ->assertJsonPath('data.students.0.score', 91.25)
            ->assertJsonPath('data.students.0.letter_grade', 'A')
            ->assertJsonPath('data.students.1.state', 'empty')
            ->assertJsonPath('data.summary', ['enrolled' => 3, 'entered' => 1, 'missing' => 2]);

        expect(AssessmentScore::count())->toBe(1);
        expect(AssessmentScore::first()->entered_by)->toBe($this->lecturer->id);
    });

    it('checks the whole request first and saves nothing if any row is wrong', function () {
        signInAs($this->lecturer);
        $kofiOther = Enrolment::where('course_offering_id', $this->nur112->id)->firstOrFail();

        $response = $this->putJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks", ['marks' => [
            ['enrolment_id' => $this->kofi->ulid, 'score' => 70],
            ['enrolment_id' => $kofiOther->ulid, 'score' => 70], // a registration for another course
        ]])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');

        expect($response->json('errors'))->toHaveKey('marks.1.enrolment_id');
        expect(AssessmentScore::count())->toBe(0);
    });

    it('refuses a withdrawn student', function () {
        signInAs($this->lecturer);
        $withdrawn = Enrolment::where('status', EnrolmentStatus::Withdrawn)->firstOrFail();

        $this->putJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks", ['marks' => [['enrolment_id' => $withdrawn->ulid, 'score' => 70]]])
            ->assertUnprocessable();
    });

    it('validates scores: 0 to 100, at most two decimals, not text', function (mixed $score) {
        signInAs($this->lecturer);

        $response = $this->putJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks", ['marks' => [['enrolment_id' => $this->kofi->ulid, 'score' => $score]]])
            ->assertUnprocessable()->assertJsonPath('code', 'validation_failed');

        expect($response->json('errors'))->toHaveKey('marks.0.score');
    })->with([101, -1, 70.123, 'abc']);

    it('requires a score key and a unique enrolment per row', function () {
        signInAs($this->lecturer);
        $url = "/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks";

        $this->putJson($url, ['marks' => [['enrolment_id' => $this->kofi->ulid]]])->assertUnprocessable();
        $this->putJson($url, ['marks' => [['enrolment_id' => $this->kofi->ulid, 'score' => 1], ['enrolment_id' => $this->kofi->ulid, 'score' => 2]]])->assertUnprocessable();
        $this->putJson($url, ['marks' => []])->assertUnprocessable();
    });

    it('is closed to lecturers of other courses and to roles without marks:enter', function () {
        signInAs($this->otherLecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 50])->assertNotFound();

        signInAs($this->head);
        putMarks($this->nur111, ['RGN/2026/0001' => 50])->assertForbidden();

        expect(AssessmentScore::count())->toBe(0);
    });
});

describe('submitting marks', function () {
    it('refuses while a registered student has no mark and says who', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72, 'RGN/2026/0002' => 55])->assertOk();

        $response = $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")
            ->assertUnprocessable()->assertJsonPath('code', 'marks_incomplete');

        expect($response->json('errors.marks.0'))->toContain('RGN/2026/0003')->toContain('Yaw Boateng');
        expect(AssessmentScore::where('status', 'submitted')->count())->toBe(0);
        expect($this->nur111->fresh()->marks_submitted_at)->toBeNull();
    });

    it('locks the marks and stores the letter grades with the scale used', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72.5, 'RGN/2026/0002' => 91, 'RGN/2026/0003' => 40])->assertOk();

        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")
            ->assertOk()
            ->assertJsonPath('data.offering_id', $this->nur111->ulid)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.marks_count', 3);

        $scores = AssessmentScore::orderBy('id')->get();
        expect($scores->pluck('status.value')->unique()->all())->toBe(['submitted'])
            ->and($scores->pluck('letter_grade')->all())->toBe(['B', 'A', 'F'])
            ->and($scores->pluck('grade_scale_id')->unique()->all())->toBe([GradeScale::first()->id])
            ->and($scores->pluck('submitted_by')->unique()->all())->toBe([$this->lecturer->id]);

        $fresh = $this->nur111->fresh();
        expect($fresh->marks_submitted_at)->not->toBeNull()->and($fresh->marks_submitted_by)->toBe($this->lecturer->id);
    });

    it('answers 409 already_submitted the second time', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 70, 'RGN/2026/0002' => 70, 'RGN/2026/0003' => 70])->assertOk();
        $url = "/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit";

        $this->postJson($url)->assertOk();
        $this->postJson($url)->assertStatus(409)->assertJsonPath('code', 'already_submitted');
    });

    it('refuses to submit a course with no registered students', function () {
        $lonely = ($this->offer)('NUR 999');
        LecturerAssignment::factory()->create(['course_offering_id' => $lonely->id, 'staff_profile_id' => $this->staff->id]);
        signInAs($this->lecturer);

        $this->postJson("/api/v1/lecturer/course-offerings/{$lonely->ulid}/marks/submit")
            ->assertUnprocessable()->assertJsonPath('code', 'marks_incomplete');
    });

    it('is closed to other lecturers, other roles and anonymous callers', function () {
        $url = "/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit";

        signInAs($this->otherLecturer);
        $this->postJson($url)->assertNotFound();

        signInAs($this->head);
        $this->postJson($url)->assertForbidden();

        expect($this->nur111->fresh()->marks_submitted_at)->toBeNull();
    });
});

describe('the lock after submission', function () {
    beforeEach(function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72, 'RGN/2026/0002' => 65, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();
    });

    it('refuses further saves with 423 grade_locked and changes nothing', function () {
        putMarks($this->nur111, ['RGN/2026/0001' => 99])->assertStatus(423)->assertJsonPath('code', 'grade_locked');
        putMarks($this->nur111, ['RGN/2026/0001' => null])->assertStatus(423)->assertJsonPath('code', 'grade_locked');

        expect(AssessmentScore::orderBy('id')->pluck('score')->map(fn ($s) => (float) $s)->all())->toBe([72.0, 65.0, 58.0]);
    });

    it('still shows the marks, now as submitted', function () {
        $this->getJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks")
            ->assertOk()
            ->assertJsonPath('data.offering.status', 'submitted')
            ->assertJsonPath('data.students.0.state', 'submitted')
            ->assertJsonPath('data.students.0.letter_grade', 'B');
    });

    it('is enforced by the database too, and the API answers 423 for it', function () {
        // A write that skips the model (as a future bug or a raw query would) hits the trigger.
        $score = AssessmentScore::firstOrFail();

        expect(fn () => DB::table('assessment_scores')->where('id', $score->id)->update(['score' => 100]))
            ->toThrow(QueryException::class, 'grade_locked');
        expect(fn () => DB::table('assessment_scores')->where('id', $score->id)->delete())
            ->toThrow(QueryException::class, 'grade_locked');

        Route::get('/api/v1/_test/raw-update', fn () => DB::table('assessment_scores')->where('id', $score->id)->update(['score' => 100]));
        $this->getJson('/api/v1/_test/raw-update')->assertStatus(423)->assertJsonPath('code', 'grade_locked');
    });
});

describe('the Head of Academics dashboard', function () {
    it('lists every course with lecturers, counts and a summary of statuses', function () {
        signInAs($this->head);

        $response = $this->getJson('/api/v1/academics/course-offerings')->assertOk();

        expect(collect($response->json('data'))->pluck('course.code')->all())->toBe(['NUR 111', 'NUR 112']);
        $response->assertJsonPath('data.0.lecturers.0.name', 'Lecturer One')
            ->assertJsonPath('data.0.enrolled_count', 3)
            ->assertJsonPath('data.1.enrolled_count', 1)
            ->assertJsonPath('meta.summary', ['not_started' => 2, 'draft' => 0, 'submitted' => 0, 'released' => 0])
            ->assertJsonPath('meta.total', 2);
    });

    it('follows a course through draft, submitted and released and filters by status', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 70])->assertOk();

        signInAs($this->head);
        $this->getJson('/api/v1/academics/course-offerings')
            ->assertJsonPath('meta.summary', ['not_started' => 1, 'draft' => 1, 'submitted' => 0, 'released' => 0]);

        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0002' => 70, 'RGN/2026/0003' => 70])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();

        signInAs($this->head);
        $submitted = $this->getJson('/api/v1/academics/course-offerings?status=submitted')->assertOk();
        expect($submitted->json('data'))->toHaveCount(1);
        $submitted->assertJsonPath('data.0.status', 'submitted')
            ->assertJsonPath('meta.summary', ['not_started' => 1, 'draft' => 0, 'submitted' => 1, 'released' => 0]);

        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        $this->getJson('/api/v1/academics/course-offerings?status=released')->assertJsonPath('data.0.course.code', 'NUR 111');
        $this->getJson('/api/v1/academics/course-offerings?status=submitted')->assertJsonCount(0, 'data');
    });

    it('defaults to the current semester and can look at another', function () {
        $next = Semester::factory()->create(['academic_year_id' => $this->semester->academic_year_id, 'number' => 2, 'starts_on' => '2027-01-18', 'ends_on' => '2027-05-09']);
        CourseOffering::factory()->create(['course_id' => Course::factory()->create(['code' => 'NUR 200'])->id, 'semester_id' => $next->id]);

        signInAs($this->head);

        expect($this->getJson('/api/v1/academics/course-offerings')->json('data'))->toHaveCount(2);
        expect($this->getJson("/api/v1/academics/course-offerings?semester={$next->ulid}")->json('data'))->toHaveCount(1);
        expect($this->getJson('/api/v1/academics/course-offerings?semester='.str_repeat('0', 26))->json('data'))->toHaveCount(0);
    });

    it('rejects an unknown status filter', function () {
        signInAs($this->head);

        $this->getJson('/api/v1/academics/course-offerings?status=bogus')->assertUnprocessable();
    });

    it('is open to academic_affairs and super_admin, closed to lecturers and students', function () {
        foreach ([Role::AcademicAffairs, Role::SuperAdmin] as $role) {
            signInAs(userWithRole($role));
            $this->getJson('/api/v1/academics/course-offerings')->assertOk();
        }

        foreach ([Role::Lecturer, Role::Student, Role::Registrar, Role::IctAdmin] as $role) {
            signInAs(userWithRole($role));
            $this->getJson('/api/v1/academics/course-offerings')->assertForbidden();
        }
    });

    it('shows submitted marks with scores for review, and nothing before submission', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72.5, 'RGN/2026/0002' => 88, 'RGN/2026/0003' => 61])->assertOk();

        signInAs($this->head);
        $this->getJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/results")
            ->assertStatus(409)->assertJsonPath('code', 'not_submitted');

        signInAs($this->lecturer);
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();

        signInAs($this->head);
        $this->getJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/results")
            ->assertOk()
            ->assertJsonPath('data.students.0.score', 72.5)
            ->assertJsonPath('data.students.0.letter_grade', 'B')
            ->assertJsonPath('data.students.1.letter_grade', 'A');
    });
});

describe('releasing results', function () {
    beforeEach(function () {
        $this->submitAll = function (CourseOffering $offering, array $scores) {
            signInAs($this->lecturer);
            putMarks($offering, $scores)->assertOk();
            test()->postJson("/api/v1/lecturer/course-offerings/{$offering->ulid}/marks/submit")->assertOk();
        };
    });

    it('needs a submitted course', function () {
        signInAs($this->head);

        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")
            ->assertStatus(409)->assertJsonPath('code', 'not_submitted');

        expect($this->nur111->fresh()->results_released_at)->toBeNull();
    });

    it('records when and by whom, and is repeatable', function () {
        ($this->submitAll)($this->nur111, ['RGN/2026/0001' => 70, 'RGN/2026/0002' => 70, 'RGN/2026/0003' => 70]);
        signInAs($this->head);
        $url = "/api/v1/academics/course-offerings/{$this->nur111->ulid}/release";

        $first = $this->postJson($url)->assertOk()
            ->assertJsonPath('data.offering_id', $this->nur111->ulid)
            ->assertJsonPath('data.status', 'released');

        $fresh = $this->nur111->fresh();
        expect($fresh->results_released_by)->toBe($this->head->id);

        $this->postJson($url)->assertOk()->assertJsonPath('data.released_at', $first->json('data.released_at'));
        expect(AuditLog::where('event', 'results.released')->count())->toBe(1);
    });

    it('is only for results:release: not lecturers, academic_affairs or students', function (Role $role) {
        ($this->submitAll)($this->nur111, ['RGN/2026/0001' => 70, 'RGN/2026/0002' => 70, 'RGN/2026/0003' => 70]);
        signInAs(userWithRole($role));

        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertForbidden();
        expect($this->nur111->fresh()->results_released_at)->toBeNull();
    })->with([Role::Lecturer, Role::AcademicAffairs, Role::Student, Role::Registrar]);

    it('keeps the marks locked after release', function () {
        ($this->submitAll)($this->nur111, ['RGN/2026/0001' => 70, 'RGN/2026/0002' => 70, 'RGN/2026/0003' => 70]);
        signInAs($this->head);
        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 10])->assertStatus(423);
        $this->getJson('/api/v1/lecturer/course-offerings')->assertJsonPath('data.0.status', 'released');
    });
});

describe('student results', function () {
    beforeEach(function () {
        $this->kofiUser = $this->kofi->student->user;
    });

    it('shows nothing before release, even after the lecturer submitted', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72, 'RGN/2026/0002' => 65, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();

        signInAs($this->kofiUser);
        $this->getJson('/api/v1/me/results')->assertOk()->assertJsonPath('data.semesters', []);
    });

    it('shows letter grade and credit hours of released courses, grouped by semester', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72, 'RGN/2026/0002' => 65, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();
        signInAs($this->head);
        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        signInAs($this->kofiUser);
        $response = $this->getJson('/api/v1/me/results')->assertOk();

        $response->assertJsonPath('data.student.index_number', 'RGN/2026/0001')
            ->assertJsonPath('data.student.programme.code', 'RGN')
            ->assertJsonPath('data.semesters.0.semester.number', 1)
            ->assertJsonPath('data.semesters.0.courses.0.code', 'NUR 111')
            ->assertJsonPath('data.semesters.0.courses.0.credit_hours', 3)
            ->assertJsonPath('data.semesters.0.courses.0.letter_grade', 'B')
            ->assertJsonPath('data.semesters.0.credit_hours_total', 3)
            ->assertJsonPath('data.grading.official', false)
            ->assertJsonPath('data.grading.is_demo', true);

        // Kofi's second course is not released, so it does not appear.
        expect($response->json('data.semesters.0.courses'))->toHaveCount(1);
    });

    it('never sends a numeric mark, a percentage or a GPA', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72.5, 'RGN/2026/0002' => 65, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();
        signInAs($this->head);
        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        signInAs($this->kofiUser);
        $text = strtolower($this->getJson('/api/v1/me/results')->assertOk()->getContent());

        foreach (['"score"', 'percent', 'average', '"gpa"', '"cgpa"', '72.5'] as $forbidden) {
            expect($text)->not->toContain($forbidden);
        }
    });

    it('shows each student only their own results', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 90, 'RGN/2026/0002' => 40, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();
        signInAs($this->head);
        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        signInAs($this->esi->student->user);
        $this->getJson('/api/v1/me/results')
            ->assertJsonPath('data.student.index_number', 'RGN/2026/0002')
            ->assertJsonPath('data.semesters.0.courses.0.letter_grade', 'F');
    });

    it('does not show a course the student withdrew from', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 90, 'RGN/2026/0002' => 40, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();
        signInAs($this->head);
        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        $this->kofi->update(['status' => EnrolmentStatus::Withdrawn]);

        signInAs($this->kofiUser);
        $this->getJson('/api/v1/me/results')->assertJsonPath('data.semesters', []);
    });

    it('is for students only and needs a student record', function () {
        foreach ([Role::Lecturer, Role::HeadOfAcademics, Role::Registrar] as $role) {
            signInAs(userWithRole($role));
            $this->getJson('/api/v1/me/results')->assertForbidden();
        }

        signInAs(userWithRole(Role::Student)); // permission but no student record
        $this->getJson('/api/v1/me/results')->assertNotFound();
    });
});

describe('the grade scale', function () {
    it('is readable by any signed-in user and is marked as not official', function (Role $role) {
        signInAs(userWithRole($role));

        $response = $this->getJson('/api/v1/grade-scale')->assertOk()
            ->assertJsonPath('data.is_demo', true)
            ->assertJsonPath('data.official', false)
            ->assertJsonPath('data.bands.0', ['letter' => 'A', 'min_score' => 80, 'max_score' => 100, 'points' => 4])
            ->assertJsonPath('data.bands.4.letter', 'F');

        expect($response->json('data.bands'))->toHaveCount(5);
    })->with([Role::Student, Role::Lecturer, Role::HeadOfAcademics, Role::Bursar]);

    it('requires a token', function () {
        $this->getJson('/api/v1/grade-scale')->assertUnauthorized();
    });

    it('answers 404 when no scale is active', function () {
        GradeScale::query()->update(['is_active' => false]);
        signInAs($this->head);

        $this->getJson('/api/v1/grade-scale')->assertNotFound();
    });

    it('stops a course being submitted when no scale is active', function () {
        GradeScale::query()->update(['is_active' => false]);
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 70, 'RGN/2026/0002' => 70, 'RGN/2026/0003' => 70])->assertOk();

        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")
            ->assertStatus(409)->assertJsonPath('code', 'no_grade_scale');
        expect(AssessmentScore::where('status', 'submitted')->count())->toBe(0);
    });
});

describe('the audit trail', function () {
    it('records submission and release without any marks', function () {
        signInAs($this->lecturer);
        putMarks($this->nur111, ['RGN/2026/0001' => 72.5, 'RGN/2026/0002' => 65, 'RGN/2026/0003' => 58])->assertOk();
        $this->postJson("/api/v1/lecturer/course-offerings/{$this->nur111->ulid}/marks/submit")->assertOk();
        signInAs($this->head);
        $this->postJson("/api/v1/academics/course-offerings/{$this->nur111->ulid}/release")->assertOk();

        $submitted = AuditLog::where('event', 'marks.submitted')->firstOrFail();
        expect($submitted->actor_id)->toBe($this->lecturer->id)
            ->and($submitted->subject_type)->toBe('course_offering')
            ->and($submitted->subject_id)->toBe($this->nur111->ulid)
            ->and($submitted->after['course'])->toBe('NUR 111')
            ->and($submitted->after['marks_count'])->toBe(3);

        $released = AuditLog::where('event', 'results.released')->firstOrFail();
        expect($released->actor_id)->toBe($this->head->id)->and($released->after['course'])->toBe('NUR 111');

        // Draft saves are not audited one by one, and no score leaks into the trail.
        expect(AuditLog::where('event', 'like', 'marks.%')->count())->toBe(1);
        expect(json_encode(AuditLog::all()->toArray()))->not->toContain('72.5');
    });
});
