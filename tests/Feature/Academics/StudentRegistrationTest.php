<?php

use App\Domain\Identity\Enums\Role;
use App\Models\AuditLog;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrolment;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use App\Models\Semester;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Security\TemporaryPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// Staff register a student (docs/api/demo-contract.md section 5).

beforeEach(function () {
    $this->department = Department::factory()->create(['code' => 'NUR']);
    $this->rgn = Programme::factory()->create(['department_id' => $this->department->id, 'code' => 'RGN', 'name' => 'Registered General Nursing']);
    $this->cohort = Cohort::factory()->create(['programme_id' => $this->rgn->id, 'intake_year' => 2026, 'current_year_of_study' => 1, 'name' => 'RGN class of 2029']);
    $this->semester = Semester::factory()->create(['is_current' => true, 'number' => 1]);
    $course = Course::factory()->create(['code' => 'NUR 111', 'department_id' => $this->department->id]);
    ProgrammeCourse::factory()->create(['programme_id' => $this->rgn->id, 'course_id' => $course->id, 'year_of_study' => 1, 'semester_number' => 1]);
    $this->offering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $this->semester->id]);
});

function newStudentBody(array $extra = []): array
{
    return array_merge([
        'name' => 'Ama Mensah',
        'email' => 'ama.mensah@example.com',
        'programme_id' => test()->rgn->ulid,
        'cohort_id' => test()->cohort->ulid,
    ], $extra);
}

describe('who may register and look up students', function () {
    it('lets the roles with accounts:create register a student', function (Role $role) {
        actingAsRole($role);

        $this->postJson('/api/v1/students', newStudentBody())->assertCreated();
    })->with([Role::SuperAdmin, Role::IctAdmin, Role::Registrar]);

    it('refuses everyone else', function (Role $role) {
        actingAsRole($role);

        $this->postJson('/api/v1/students', newStudentBody())->assertForbidden()->assertJsonPath('code', 'forbidden');

        expect(StudentProfile::count())->toBe(0)->and(User::where('email', 'ama.mensah@example.com')->exists())->toBeFalse();
    })->with([Role::HeadOfAcademics, Role::AcademicAffairs, Role::Lecturer, Role::Bursar, Role::Student]);

    it('lets the roles with students:view search and read students', function (Role $role) {
        actingAsRole($role);
        $student = StudentProfile::factory()->create();

        $this->getJson('/api/v1/students')->assertOk();
        $this->getJson("/api/v1/students/{$student->ulid}")->assertOk()->assertJsonPath('data.id', $student->ulid);
    })->with([Role::SuperAdmin, Role::HeadOfAcademics, Role::IctAdmin, Role::Registrar, Role::AcademicAffairs]);

    it('keeps students and lecturers out of other students records', function (Role $role) {
        actingAsRole($role);
        $student = StudentProfile::factory()->create();

        $this->getJson('/api/v1/students')->assertForbidden();
        $this->getJson("/api/v1/students/{$student->ulid}")->assertForbidden();
    })->with([Role::Student, Role::Lecturer, Role::Bursar]);

    it('needs a token', function () {
        $this->postJson('/api/v1/students', newStudentBody())->assertUnauthorized();
    });
});

describe('registering a student', function () {
    beforeEach(fn () => $this->staff = actingAsRole(Role::Registrar));

    it('creates the account, the record and the index number in one step', function () {
        $response = $this->postJson('/api/v1/students', newStudentBody())->assertCreated();

        $response->assertJsonPath('data.index_number', 'RGN/2026/0001')
            ->assertJsonPath('data.user.name', 'Ama Mensah')
            ->assertJsonPath('data.user.email', 'ama.mensah@example.com')
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.user.must_change_password', true)
            ->assertJsonPath('data.programme.code', 'RGN')
            ->assertJsonPath('data.cohort.intake_year', 2026)
            ->assertJsonPath('data.year_of_study', 1)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.registered_offerings', 0);

        $user = User::where('email', 'ama.mensah@example.com')->firstOrFail();

        expect($user->hasRole('student'))->toBeTrue()
            ->and($user->studentProfile->ulid)->toBe($response->json('data.id'))
            ->and(strlen($response->json('data.id')))->toBe(26);
    });

    it('gives each student the next index number', function () {
        $first = $this->postJson('/api/v1/students', newStudentBody())->assertCreated()->json('data.index_number');
        $second = $this->postJson('/api/v1/students', newStudentBody(['email' => 'kofi@example.com', 'name' => 'Kofi Owusu']))->assertCreated()->json('data.index_number');

        expect([$first, $second])->toBe(['RGN/2026/0001', 'RGN/2026/0002']);
    });

    it('stores the Ministry of Health id as a reference only', function () {
        $this->postJson('/api/v1/students', newStudentBody(['moh_membership_id' => 'MOH-42']))->assertCreated()->assertJsonPath('data.moh_membership_id', 'MOH-42');
    });

    it('can find the cohort from the year when only one cohort matches', function () {
        $this->postJson('/api/v1/students', newStudentBody(['cohort_id' => null, 'year_of_study' => 1]))
            ->assertCreated()->assertJsonPath('data.cohort.id', $this->cohort->ulid);
    });

    it('refuses bad input with field errors', function (array $change, string $field) {
        $this->postJson('/api/v1/students', newStudentBody($change))
            ->assertStatus(422)->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrorFor($field, 'errors');

        expect(StudentProfile::count())->toBe(0);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'bad email' => [['email' => 'not-an-email'], 'email'],
        'unknown programme' => [['programme_id' => '01jd2k9m4q7x3v8n5r6t1y0zzz'], 'programme_id'],
        'unknown cohort' => [['cohort_id' => '01jd2k9m4q7x3v8n5r6t1y0zzz'], 'cohort_id'],
        'no cohort and no year' => [['cohort_id' => null], 'cohort_id'],
        'year with no matching cohort' => [['cohort_id' => null, 'year_of_study' => 3], 'cohort_id'],
        'year out of range' => [['year_of_study' => 11], 'year_of_study'],
    ]);

    it('refuses a cohort that belongs to another programme', function () {
        $other = Cohort::factory()->create();

        $this->postJson('/api/v1/students', newStudentBody(['cohort_id' => $other->ulid]))
            ->assertStatus(422)->assertJsonValidationErrorFor('cohort_id', 'errors');
    });

    it('refuses an email that already has an account, in any letter case', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/v1/students', newStudentBody(['email' => 'TAKEN@example.com']))
            ->assertStatus(422)->assertJsonValidationErrorFor('email', 'errors');
    });

    it('registers the first courses too, without needing a registration window', function () {
        $response = $this->postJson('/api/v1/students', newStudentBody(['course_offering_ids' => [$this->offering->ulid]]))->assertCreated();

        $response->assertJsonPath('data.registered_offerings', 1);

        expect(Enrolment::count())->toBe(1)
            ->and(AuditLog::where('event', 'enrolment.created')->count())->toBe(1);
    });

    it('creates nothing at all when a first course is refused', function () {
        $stranger = CourseOffering::factory()->create(['semester_id' => $this->semester->id]); // not in RGN's curriculum

        $this->postJson('/api/v1/students', newStudentBody(['course_offering_ids' => [$this->offering->ulid, $stranger->ulid]]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'registration_rejected')
            ->assertJsonValidationErrorFor('course_offering_ids.1', 'errors');

        expect(StudentProfile::count())->toBe(0)
            ->and(User::where('email', 'ama.mensah@example.com')->exists())->toBeFalse()
            ->and(Enrolment::count())->toBe(0)
            ->and(AuditLog::where('event', 'student.registered')->count())->toBe(0);
    });
});

describe('the temporary password', function () {
    beforeEach(fn () => $this->staff = actingAsRole(Role::Registrar));

    it('is returned once, in meta, and the student can sign in with it but must change it', function () {
        $password = $this->postJson('/api/v1/students', newStudentBody())->assertCreated()->json('meta.temporary_password');

        expect($password)->toBeString()->toMatch('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/');

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'ama.mensah@example.com', 'password' => $password])->assertOk();
        $login->assertJsonPath('data.user.must_change_password', true);

        $token = $login->json('data.token');
        apiAs($token)->getJson('/api/v1/students')->assertForbidden();
        apiAs($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.roles', ['student']);
    });

    it('is not shown when the student is read again, in a list or on its own', function () {
        $created = $this->postJson('/api/v1/students', newStudentBody())->assertCreated();
        $password = $created->json('meta.temporary_password');
        $id = $created->json('data.id');

        $show = $this->getJson("/api/v1/students/{$id}")->assertOk();
        $list = $this->getJson('/api/v1/students')->assertOk();

        expect($show->getContent())->not->toContain($password)
            ->and($list->getContent())->not->toContain($password)
            ->and($show->json('meta'))->toBeNull();
    });

    it('is stored only as a hash and never written to the audit log', function () {
        $password = $this->postJson('/api/v1/students', newStudentBody(['course_offering_ids' => [$this->offering->ulid]]))->assertCreated()->json('meta.temporary_password');

        $stored = User::where('email', 'ama.mensah@example.com')->value('password');
        $audit = AuditLog::all()->toJson();

        expect($stored)->not->toBe($password)->and(Hash::check($password, $stored))->toBeTrue()
            ->and($audit)->not->toContain($password)
            ->and($audit)->toContain('student.registered');
    });

    it('is the same again when a retry carries the same Idempotency-Key, and no second student appears', function () {
        $headers = ['Idempotency-Key' => 'register-ama-0001'];

        $first = $this->postJson('/api/v1/students', newStudentBody(), $headers)->assertCreated();
        $second = $this->postJson('/api/v1/students', newStudentBody(), $headers)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        expect($second->json('meta.temporary_password'))->toBe($first->json('meta.temporary_password'))
            ->and(StudentProfile::count())->toBe(1)
            // ...but never readable in the database:
            ->and(DB::table('idempotency_keys')->value('response'))->not->toContain($first->json('meta.temporary_password'));
    });

    it('records who registered the student, without secrets', function () {
        $this->postJson('/api/v1/students', newStudentBody())->assertCreated();

        $entry = AuditLog::where('event', 'student.registered')->firstOrFail();

        expect($entry->actor_id)->toBe($this->staff->id)
            ->and($entry->subject_type)->toBe('student')
            ->and($entry->after)->toBe([
                'index_number' => 'RGN/2026/0001', 'email' => 'ama.mensah@example.com', 'programme' => 'RGN',
                'cohort' => 'RGN class of 2029', 'year_of_study' => 1,
            ]);
    });
});

describe('looking students up', function () {
    beforeEach(function () {
        actingAsRole(Role::Registrar);
        $this->midwifery = Department::factory()->create(['code' => 'MID']);
        $this->rm = Programme::factory()->create(['department_id' => $this->midwifery->id, 'code' => 'RM']);
        $rmCohort = Cohort::factory()->create(['programme_id' => $this->rm->id, 'intake_year' => 2025, 'current_year_of_study' => 2]);

        $make = fn (string $name, string $email, string $index, Programme $programme, Cohort $cohort, int $year) => StudentProfile::factory()->create([
            'user_id' => User::factory()->create(['name' => $name, 'email' => $email])->id,
            'index_number' => $index, 'programme_id' => $programme->id, 'cohort_id' => $cohort->id, 'year_of_study' => $year,
        ]);

        $this->ama = $make('Ama Mensah', 'ama@example.com', 'RGN/2026/0001', $this->rgn, $this->cohort, 1);
        $this->kofi = $make('Kofi Owusu', 'kofi@example.com', 'RGN/2026/0002', $this->rgn, $this->cohort, 1);
        $this->esi = $make('Esi Darko', 'esi@example.com', 'RM/2025/0001', $this->rm, $rmCohort, 2);
        Enrolment::factory()->create(['student_profile_id' => $this->ama->id, 'course_offering_id' => $this->offering->id]);
        $this->rmCohort = $rmCohort;
    });

    it('lists students by index number, with their registrations counted', function () {
        $response = $this->getJson('/api/v1/students')->assertOk();

        expect(collect($response->json('data'))->pluck('index_number')->all())->toBe(['RGN/2026/0001', 'RGN/2026/0002', 'RM/2025/0001'])
            ->and($response->json('data.0.registered_offerings'))->toBe(1)
            ->and($response->json('data.1.registered_offerings'))->toBe(0)
            ->and($response->json('meta.total'))->toBe(3);
    });

    it('searches name, email and index number', function () {
        $find = fn (string $q) => collect($this->getJson('/api/v1/students?q='.urlencode($q))->assertOk()->json('data'))->pluck('index_number')->all();

        expect($find('mensah'))->toBe(['RGN/2026/0001'])
            ->and($find('kofi@'))->toBe(['RGN/2026/0002'])
            ->and($find('RM/2025'))->toBe(['RM/2025/0001'])
            ->and($find('nobody'))->toBe([]);
    });

    it('filters by programme, year, cohort and department', function () {
        $idx = fn (string $query) => collect($this->getJson("/api/v1/students?{$query}")->assertOk()->json('data'))->pluck('index_number')->all();

        expect($idx("programme={$this->rm->ulid}"))->toBe(['RM/2025/0001'])
            ->and($idx('year=1'))->toBe(['RGN/2026/0001', 'RGN/2026/0002'])
            ->and($idx("cohort={$this->rmCohort->ulid}"))->toBe(['RM/2025/0001'])
            ->and($idx("department={$this->department->ulid}"))->toBe(['RGN/2026/0001', 'RGN/2026/0002'])
            ->and($idx("department={$this->midwifery->ulid}&year=1"))->toBe([]);
    });

    it('pages, and refuses bad filters', function () {
        $this->getJson('/api/v1/students?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);

        foreach (['per_page=1000', 'year=0', 'programme=short'] as $bad) {
            $this->getJson("/api/v1/students?{$bad}")->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        }
    });

    it('shows one student, and 404 for an unknown or numeric id', function () {
        $this->getJson("/api/v1/students/{$this->esi->ulid}")->assertOk()
            ->assertJsonPath('data.user.email', 'esi@example.com')
            ->assertJsonPath('data.programme.code', 'RM')
            ->assertJsonPath('data.year_of_study', 2);

        $this->getJson('/api/v1/students/01jd2k9m4q7x3v8n5r6t1y0zzz')->assertNotFound();
        $this->getJson("/api/v1/students/{$this->esi->id}")->assertNotFound();
    });
});

describe('between colleges', function () {
    it('lets a college see and register only its own students', function () {
        config(['tenancy.enabled' => true]);
        $a = newTenant('college-a');
        $b = newTenant('college-b');

        [$registrarA, $studentA] = tenancyContext()->runAs($a, fn () => [userWithRole(Role::Registrar), StudentProfile::factory()->create()]);
        $studentB = tenancyContext()->runAs($b, fn () => StudentProfile::factory()->create());
        $token = $registrarA->createToken('t')->plainTextToken;

        $ids = collect(apiAs($token)->getJson('/api/v1/students')->assertOk()->json('data'))->pluck('id')->all();

        expect($ids)->toBe([$studentA->ulid]);

        apiAs($token)->getJson("/api/v1/students/{$studentB->ulid}")->assertNotFound();
    });
});

describe('issuing a new temporary password', function () {
    beforeEach(function () {
        $this->target = User::factory()->create(['email' => 'locked.out@example.com']);
        $this->target->assignRole(Role::Student->value);
    });

    it('lets ict_admin and super_admin do it, and the person must change it', function (Role $role) {
        actingAsRole($role);
        $oldToken = $this->target->createToken('phone')->plainTextToken;

        $response = $this->postJson("/api/v1/users/{$this->target->ulid}/reset-password")->assertOk();
        $password = $response->json('meta.temporary_password');

        $response->assertJsonPath('data.id', $this->target->ulid)->assertJsonPath('data.must_change_password', true);

        expect($password)->toMatch('/^[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}$/')
            ->and($this->target->fresh()->must_change_password)->toBeTrue()
            ->and($this->target->tokens()->count())->toBe(0);

        apiAs($oldToken)->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/login', ['email' => 'locked.out@example.com', 'password' => $password])->assertOk()->assertJsonPath('data.user.must_change_password', true);
        $this->postJson('/api/v1/auth/login', ['email' => 'locked.out@example.com', 'password' => 'password'])->assertUnauthorized();
    })->with([Role::SuperAdmin, Role::IctAdmin]);

    it('is for ict_admin and super_admin only', function (Role $role) {
        actingAsRole($role);

        $this->postJson("/api/v1/users/{$this->target->ulid}/reset-password")->assertForbidden()->assertJsonPath('code', 'forbidden');
    })->with([Role::HeadOfAcademics, Role::Registrar, Role::AcademicAffairs, Role::Lecturer, Role::Student]);

    it('never records the password in the audit log', function () {
        $actor = actingAsRole(Role::IctAdmin);

        $password = $this->postJson("/api/v1/users/{$this->target->ulid}/reset-password")->assertOk()->json('meta.temporary_password');
        $entry = AuditLog::where('event', 'user.password_reset_by_admin')->firstOrFail();

        expect($entry->actor_id)->toBe($actor->id)
            ->and($entry->subject_id)->toBe($this->target->ulid)
            ->and(AuditLog::all()->toJson())->not->toContain($password);
    });

    it('cannot be used on yourself, or on a super_admin by anyone else', function () {
        $ict = actingAsRole(Role::IctAdmin);
        $super = userWithRole(Role::SuperAdmin);

        $this->postJson("/api/v1/users/{$ict->ulid}/reset-password")->assertForbidden();
        $this->postJson("/api/v1/users/{$super->ulid}/reset-password")->assertForbidden();
    });

    it('is refused for accounts that are not active', function () {
        actingAsRole(Role::IctAdmin);
        $invited = User::factory()->invited()->create();
        $disabled = User::factory()->disabled()->create();

        $this->postJson("/api/v1/users/{$invited->ulid}/reset-password")->assertStatus(409)->assertJsonPath('code', 'conflict');
        $this->postJson("/api/v1/users/{$disabled->ulid}/reset-password")->assertStatus(409);
    });

    it('answers 404 for an unknown user', function () {
        actingAsRole(Role::IctAdmin);

        $this->postJson('/api/v1/users/01jd2k9m4q7x3v8n5r6t1y0zzz/reset-password')->assertNotFound();
    });
});

describe('TemporaryPassword', function () {
    it('always meets the password policy and never repeats', function () {
        $seen = collect(range(1, 300))->map(fn () => TemporaryPassword::generate());

        foreach ($seen as $password) {
            expect(strlen($password))->toBe(14)
                ->and($password)->toMatch('/[A-Z]/')->toMatch('/[a-z]/')->toMatch('/[0-9]/')
                ->and($password)->not->toMatch('/[0OlI1]/');
        }

        expect($seen->unique())->toHaveCount(300);
    });
});
