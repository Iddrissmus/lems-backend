<?php

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Identity\Enums\Role;
use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrolment;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StudentProfile;
use Laravel\Sanctum\Sanctum;

// A student's own side of registration (docs/api/demo-contract.md section 6).

beforeEach(function () {
    $this->department = Department::factory()->create(['code' => 'NUR']);
    $this->rgn = Programme::factory()->create(['department_id' => $this->department->id, 'code' => 'RGN']);
    $this->rm = Programme::factory()->create(['department_id' => $this->department->id, 'code' => 'RM']);
    $this->cohort = Cohort::factory()->create(['programme_id' => $this->rgn->id, 'intake_year' => 2026, 'current_year_of_study' => 1]);
    $this->semester = Semester::factory()->create(['is_current' => true, 'number' => 1]);
    $this->semesterTwo = Semester::factory()->create(['academic_year_id' => $this->semester->academic_year_id, 'number' => 2, 'starts_on' => '2027-01-18', 'ends_on' => '2027-05-09']);

    $offer = function (string $code, string $title, Programme $programme, int $year, Semester $semester, ?Semester $offeredIn = null, float $credits = 3) {
        $course = Course::factory()->create(['code' => $code, 'title' => $title, 'department_id' => $this->department->id, 'credit_hours' => $credits]);
        ProgrammeCourse::factory()->create(['programme_id' => $programme->id, 'course_id' => $course->id, 'year_of_study' => $year, 'semester_number' => $semester->number]);

        return CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => ($offeredIn ?? $semester)->id]);
    };

    $this->nur111 = $offer('NUR 111', 'Foundations of Nursing', $this->rgn, 1, $this->semester);
    $this->nur112 = $offer('NUR 112', 'Anatomy and Physiology I', $this->rgn, 1, $this->semester, credits: 4);
    $this->nur211 = $offer('NUR 211', 'Medical-Surgical Nursing I', $this->rgn, 2, $this->semester); // year 2
    $this->mid111 = $offer('MID 111', 'Foundations of Midwifery', $this->rm, 1, $this->semester); // other programme
    $this->nur113 = $offer('NUR 113', 'Communication Skills', $this->rgn, 1, $this->semesterTwo); // semester 2

    $this->user = userWithRole(Role::Student, ['email' => 'student@example.com', 'name' => 'Ama Mensah']);
    $this->student = StudentProfile::factory()->create(['user_id' => $this->user->id, 'programme_id' => $this->rgn->id, 'cohort_id' => $this->cohort->id, 'year_of_study' => 1, 'index_number' => 'RGN/2026/0001']);
    $this->window = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDays(10)]);

    Sanctum::actingAs($this->user);
});

describe('who this is for', function () {
    it('works for a student with a record', function () {
        foreach (['me/student', 'me/registration', 'me/course-offerings'] as $path) {
            $this->getJson("/api/v1/{$path}")->assertOk();
        }
    });

    it('is closed to roles without registration:self', function (Role $role) {
        actingAsRole($role);

        foreach (['me/student', 'me/registration', 'me/course-offerings'] as $path) {
            $this->getJson("/api/v1/{$path}")->assertForbidden()->assertJsonPath('code', 'forbidden');
        }

        $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])->assertForbidden();
    })->with([Role::Registrar, Role::Lecturer, Role::HeadOfAcademics, Role::Bursar]);

    it('answers 404 for an account that has the permission but no student record', function () {
        actingAsRole(Role::Student); // a new student user with no profile

        $this->getJson('/api/v1/me/student')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])->assertNotFound();
    });
});

describe('my record', function () {
    it('shows my own student record', function () {
        $this->getJson('/api/v1/me/student')->assertOk()
            ->assertJsonPath('data.id', $this->student->ulid)
            ->assertJsonPath('data.index_number', 'RGN/2026/0001')
            ->assertJsonPath('data.user.email', 'student@example.com')
            ->assertJsonPath('data.programme.code', 'RGN')
            ->assertJsonPath('data.year_of_study', 1)
            ->assertJsonPath('data.registered_offerings', 0);
    });

    it('never shows another student', function () {
        $other = StudentProfile::factory()->create();

        $response = $this->getJson('/api/v1/me/student')->assertOk();

        expect($response->json('data.id'))->toBe($this->student->ulid)->not->toBe($other->ulid);
    });
});

describe('courses I can register for', function () {
    it('lists only my programme, my year, this semester, with the registered flag', function () {
        Enrolment::factory()->create(['student_profile_id' => $this->student->id, 'course_offering_id' => $this->nur112->id]);

        $response = $this->getJson('/api/v1/me/course-offerings')->assertOk();

        expect(collect($response->json('data'))->map(fn ($o) => [$o['course']['code'], $o['registered']])->all())
            ->toBe([['NUR 111', false], ['NUR 112', true]]);

        $response->assertJsonPath('data.0.id', $this->nur111->ulid)
            ->assertJsonPath('data.1.course.credit_hours', 4)
            ->assertJsonPath('data.0.classes.0.programme.code', 'RGN');
    });

    it('searches by code or title', function () {
        $find = fn (string $q) => collect($this->getJson('/api/v1/me/course-offerings?q='.urlencode($q))->assertOk()->json('data'))->pluck('course.code')->all();

        expect($find('anatomy'))->toBe(['NUR 112'])->and($find('NUR 1'))->toBe(['NUR 111', 'NUR 112'])->and($find('zzz'))->toBe([]);
    });

    it('does not count a withdrawn course as registered', function () {
        Enrolment::factory()->create(['student_profile_id' => $this->student->id, 'course_offering_id' => $this->nur111->id, 'status' => EnrolmentStatus::Withdrawn]);

        expect($this->getJson('/api/v1/me/course-offerings?q=NUR 111')->json('data.0.registered'))->toBeFalse();
    });

    it('is empty when there is no current semester', function () {
        Semester::query()->update(['is_current' => false]);

        $this->getJson('/api/v1/me/course-offerings')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('my registration this semester', function () {
    it('shows the open window and what I registered', function () {
        Enrolment::factory()->create(['student_profile_id' => $this->student->id, 'course_offering_id' => $this->nur111->id]);

        $this->getJson('/api/v1/me/registration')->assertOk()
            ->assertJsonPath('data.semester.id', $this->semester->ulid)
            ->assertJsonPath('data.semester.number', 1)
            ->assertJsonPath('data.window.status', 'open')
            ->assertJsonPath('data.registered.0.code', 'NUR 111')
            ->assertJsonPath('data.registered.0.status', 'registered')
            ->assertJsonCount(1, 'data.registered');
    });

    it('leaves out courses I withdrew from and courses of other semesters', function () {
        Enrolment::factory()->create(['student_profile_id' => $this->student->id, 'course_offering_id' => $this->nur111->id, 'status' => EnrolmentStatus::Withdrawn]);
        Enrolment::factory()->create(['student_profile_id' => $this->student->id, 'course_offering_id' => $this->nur113->id]);

        $this->getJson('/api/v1/me/registration')->assertOk()->assertJsonCount(0, 'data.registered');
    });

    it('picks the window that matters: open, else the next to open, else the latest closed', function () {
        $upcoming = RegistrationWindow::factory()->create(['semester_id' => $this->semester->id, 'opens_at' => now()->addDays(20), 'closes_at' => now()->addDays(30)]);

        expect($this->getJson('/api/v1/me/registration')->json('data.window.status'))->toBe('open');

        $this->window->forceFill(['closed_at' => now()])->save();
        expect($this->getJson('/api/v1/me/registration')->json('data.window.status'))->toBe('not_yet_open')
            ->and($this->getJson('/api/v1/me/registration')->json('data.window.opens_at'))->toBe($upcoming->opens_at->toIso8601String());

        $upcoming->delete();
        expect($this->getJson('/api/v1/me/registration')->json('data.window.status'))->toBe('closed');
    });

    it('shows no window when none covers my programme and year', function () {
        $this->window->forceFill(['audience' => [['programme_id' => $this->rm->id, 'year_of_study' => 1]]])->save();

        $this->getJson('/api/v1/me/registration')->assertOk()->assertJsonPath('data.window', null);
    });

    it('copes with no current semester', function () {
        Semester::query()->update(['is_current' => false]);

        $this->getJson('/api/v1/me/registration')->assertOk()->assertJsonPath('data.semester', null)->assertJsonPath('data.window', null)->assertJsonCount(0, 'data.registered');
    });
});

describe('registering for courses', function () {
    it('registers the courses and records each in the audit log', function () {
        $response = $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid, $this->nur112->ulid]])->assertCreated();

        expect(collect($response->json('data'))->pluck('code')->all())->toBe(['NUR 111', 'NUR 112']);

        $response->assertJsonPath('data.0.course_offering_id', $this->nur111->ulid)
            ->assertJsonPath('data.0.title', 'Foundations of Nursing')
            ->assertJsonPath('data.0.credit_hours', 3)
            ->assertJsonPath('data.0.status', 'registered');

        $entries = AuditLog::where('event', 'enrolment.created')->get();

        expect($entries)->toHaveCount(2)
            ->and($entries->every(fn ($e) => $e->actor_id === $this->user->id && $e->subject_type === 'enrolment'))->toBeTrue()
            ->and($entries->first()->after['student'])->toBe('RGN/2026/0001')
            ->and(Enrolment::where('student_profile_id', $this->student->id)->count())->toBe(2);
    });

    it('is all or nothing: one refused course means none are registered, with a reason for each refusal', function () {
        $response = $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid, $this->nur211->ulid, $this->mid111->ulid, $this->nur113->ulid]])
            ->assertStatus(422)->assertJsonPath('code', 'registration_rejected')->assertJsonPath('message', 'Some courses could not be registered.');

        $errors = $response->json('errors');

        expect(array_keys($errors))->toBe(['course_offering_ids.1', 'course_offering_ids.2', 'course_offering_ids.3'])
            ->and($errors['course_offering_ids.1'][0])->toContain("your programme's curriculum")
            ->and($errors['course_offering_ids.2'][0])->toContain("your programme's curriculum")
            ->and($errors['course_offering_ids.3'][0])->toBe('This course is not offered this semester.')
            ->and(Enrolment::count())->toBe(0)
            ->and(AuditLog::where('event', 'enrolment.created')->count())->toBe(0);
    });

    it('refuses a course that is not there', function () {
        $response = $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => ['01jd2k9m4q7x3v8n5r6t1y0zzz']])
            ->assertStatus(422)->assertJsonPath('code', 'registration_rejected');

        expect($response->json('errors')['course_offering_ids.0'])->toBe(['This course is not offered this semester.']);
    });

    it('refuses a course I am already registered for', function () {
        $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])->assertCreated();

        $response = $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur112->ulid, $this->nur111->ulid]])
            ->assertStatus(422)->assertJsonPath('code', 'registration_rejected');

        expect($response->json('errors')['course_offering_ids.1'])->toBe(['You are already registered for this course.'])
            ->and(Enrolment::count())->toBe(1);
    });

    it('refuses everything while no window is open for me', function (callable $close) {
        $close($this);

        $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])
            ->assertStatus(422)->assertJsonPath('code', 'registration_closed');

        expect(Enrolment::count())->toBe(0);
    })->with([
        'closed early' => [fn ($t) => $t->window->forceFill(['closed_at' => now()])->save()],
        'expired' => [fn ($t) => $t->window->forceFill(['opens_at' => now()->subDays(20), 'closes_at' => now()->subDay()])->save()],
        'not open yet' => [fn ($t) => $t->window->forceFill(['opens_at' => now()->addDay(), 'closes_at' => now()->addDays(9)])->save()],
        'for another programme' => [fn ($t) => $t->window->forceFill(['audience' => [['programme_id' => $t->rm->id, 'year_of_study' => 1]]])->save()],
        'for another year' => [fn ($t) => $t->window->forceFill(['audience' => [['programme_id' => $t->rgn->id, 'year_of_study' => 2]]])->save()],
        'no window at all' => [fn ($t) => $t->window->delete()],
    ]);

    it('works when the audience names my programme and year', function () {
        $this->window->forceFill(['audience' => [['programme_id' => $this->rgn->id, 'year_of_study' => 1]]])->save();

        $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])->assertCreated();
    });

    it('refuses bad input before looking at courses', function (array $body) {
        $this->postJson('/api/v1/me/registrations', $body)->assertStatus(422)->assertJsonPath('code', 'validation_failed');
    })->with([
        'nothing' => [[]],
        'empty list' => [['course_offering_ids' => []]],
        'not a list' => [['course_offering_ids' => 'abc']],
        'a repeated id' => [['course_offering_ids' => ['01jd2k9m4q7x3v8n5r6t1y0zab', '01jd2k9m4q7x3v8n5r6t1y0zab']]],
        'a short id' => [['course_offering_ids' => ['short']]],
    ]);

    it('lets me register again for a course I withdrew from, reusing the same registration', function () {
        $first = $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])->assertCreated()->json('data.0.id');
        $this->deleteJson("/api/v1/me/registrations/{$first}")->assertOk();

        $again = $this->postJson('/api/v1/me/registrations', ['course_offering_ids' => [$this->nur111->ulid]])->assertCreated();

        expect($again->json('data.0.id'))->toBe($first)
            ->and($again->json('data.0.status'))->toBe('registered')
            ->and(Enrolment::count())->toBe(1);
    });

    it('does nothing twice when retried with the same Idempotency-Key', function () {
        $body = ['course_offering_ids' => [$this->nur111->ulid]];
        $headers = ['Idempotency-Key' => 'register-courses-0001'];

        $first = $this->postJson('/api/v1/me/registrations', $body, $headers)->assertCreated();
        $second = $this->postJson('/api/v1/me/registrations', $body, $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect($second->json())->toBe($first->json())
            ->and(Enrolment::count())->toBe(1)
            ->and(AuditLog::where('event', 'enrolment.created')->count())->toBe(1);
    });
});

describe('withdrawing', function () {
    beforeEach(function () {
        $this->enrolment = Enrolment::factory()->create(['student_profile_id' => $this->student->id, 'course_offering_id' => $this->nur111->id]);
    });

    it('keeps the registration with status withdrawn, and audits it', function () {
        $this->deleteJson("/api/v1/me/registrations/{$this->enrolment->ulid}")->assertOk()
            ->assertJsonPath('data.id', $this->enrolment->ulid)
            ->assertJsonPath('data.status', 'withdrawn')
            ->assertJsonPath('data.code', 'NUR 111');

        $entry = AuditLog::where('event', 'enrolment.withdrawn')->firstOrFail();

        expect($this->enrolment->fresh()->status)->toBe(EnrolmentStatus::Withdrawn)
            ->and($entry->actor_id)->toBe($this->user->id)
            ->and($entry->after)->toBe(['status' => 'withdrawn']);
    });

    it('cannot be done twice', function () {
        $this->deleteJson("/api/v1/me/registrations/{$this->enrolment->ulid}")->assertOk();

        $this->deleteJson("/api/v1/me/registrations/{$this->enrolment->ulid}")->assertStatus(409)->assertJsonPath('code', 'conflict');
    });

    it('needs an open window', function () {
        $this->window->forceFill(['closed_at' => now()])->save();

        $this->deleteJson("/api/v1/me/registrations/{$this->enrolment->ulid}")->assertStatus(422)->assertJsonPath('code', 'registration_closed');

        expect($this->enrolment->fresh()->status)->toBe(EnrolmentStatus::Registered);
    });

    it('is refused once marks exist for the course', function () {
        $assessment = Assessment::factory()->create(['course_offering_id' => $this->nur111->id]);
        AssessmentScore::factory()->create(['assessment_id' => $assessment->id, 'enrolment_id' => $this->enrolment->id]);

        $this->deleteJson("/api/v1/me/registrations/{$this->enrolment->ulid}")->assertStatus(409)->assertJsonPath('code', 'conflict');

        expect($this->enrolment->fresh()->status)->toBe(EnrolmentStatus::Registered);
    });

    it('answers 404 for someone elses registration, exactly like one that does not exist', function () {
        $other = Enrolment::factory()->create(['course_offering_id' => $this->nur111->id]);

        $this->deleteJson("/api/v1/me/registrations/{$other->ulid}")->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->deleteJson('/api/v1/me/registrations/01jd2k9m4q7x3v8n5r6t1y0zzz')->assertNotFound();

        expect($other->fresh()->status)->toBe(EnrolmentStatus::Registered);
    });

    it('never accepts the numeric id', function () {
        $this->deleteJson("/api/v1/me/registrations/{$this->enrolment->id}")->assertNotFound();
    });
});
