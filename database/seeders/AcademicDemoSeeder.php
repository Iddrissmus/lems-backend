<?php

namespace Database\Seeders;

use App\Domain\Academics\Actions\IssueIndexNumber;
use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Domain\Academics\Enums\StudentStatus;
use App\Domain\Identity\Enums\Role;
use App\Models\AcademicYear;
use App\Models\Assessment;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Enrolment;
use App\Models\GradeScale;
use App\Models\LecturerAssignment;
use App\Models\Programme;
use App\Models\ProgrammeCourse;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * SYNTHETIC demo data for the academic model: 3 departments, 2 programmes, 1
 * academic year with 2 sixteen-week semesters, 6 cohorts, 12 courses, about 40
 * students, 8 lecturers, a synthetic grade scale and an open registration window.
 *
 * Everything here is invented (names are randomly combined, emails are
 *
 * @demo.test). Nothing in it is a College rule: credit hours, dates and the grade
 * scale are placeholders. Local environment only. Safe to run again.
 *
 * The demo student (student@demo.test) is left WITHOUT course registrations on
 * purpose, so registering can be shown live.
 */
class AcademicDemoSeeder extends Seeder
{
    private const GIVEN = ['Kwame', 'Ama', 'Kofi', 'Abena', 'Yaw', 'Akosua', 'Kojo', 'Adwoa', 'Kwesi', 'Efua', 'Nana', 'Esi', 'Fiifi', 'Maame', 'Kwabena', 'Afia', 'Yaa', 'Kobby', 'Araba', 'Ekow'];

    private const FAMILY = ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Appiah', 'Darko', 'Amoah', 'Sarpong', 'Acheampong', 'Quaye', 'Tetteh', 'Ofori', 'Annan', 'Bonsu', 'Agyei', 'Danso', 'Frimpong', 'Ntim', 'Badu', 'Larbi'];

    private ?string $passwordHash = null;

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('AcademicDemoSeeder is for the local environment only (synthetic data).');
        }

        $this->call([RolesAndPermissionsSeeder::class, DemoUsersSeeder::class]);
        fake()->seed(2026);

        $departments = $this->departments();
        $programmes = $this->programmes($departments);
        [$year, $semesters] = $this->calendar();
        $cohorts = $this->cohorts($programmes);
        $courses = $this->courses($departments, $programmes);
        $offerings = $this->offerings($courses, $semesters[1]);
        $lecturers = $this->lecturers($departments);
        $this->assignLecturers($offerings, $lecturers);
        $this->assessments($offerings);
        $this->students($programmes, $cohorts, $offerings);
        $this->gradeScale();
        $this->registrationWindow($semesters[1]);
    }

    /**
     * @return array<string, Department>
     */
    private function departments(): array
    {
        $out = [];

        foreach (['NUR' => 'Nursing', 'MID' => 'Midwifery', 'SDV' => 'Student Development'] as $code => $name) {
            $out[$code] = Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }

        return $out;
    }

    /**
     * @param  array<string, Department>  $departments
     * @return array<string, Programme>
     */
    private function programmes(array $departments): array
    {
        $out = [];

        foreach ([['RGN', 'Registered General Nursing', 'NUR'], ['RM', 'Registered Midwifery', 'MID']] as [$code, $name, $dept]) {
            $out[$code] = Programme::firstOrCreate(['code' => $code], ['name' => $name, 'department_id' => $departments[$dept]->id, 'duration_years' => 3]);
        }

        return $out;
    }

    /**
     * @return array{0: AcademicYear, 1: array<int, Semester>}
     */
    private function calendar(): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['starts_on' => '2026-09-07', 'ends_on' => '2027-08-29', 'is_current' => true]);
        $semesters = [];

        foreach ([1 => '2026-09-07', 2 => '2027-01-18'] as $number => $start) {
            $starts = Carbon::parse($start);
            $semesters[$number] = Semester::firstOrCreate(
                ['academic_year_id' => $year->id, 'number' => $number],
                ['starts_on' => $starts, 'length_weeks' => 16, 'ends_on' => $starts->copy()->addWeeks(16)->subDay(), 'is_current' => $number === 1],
            );
        }

        return [$year, $semesters];
    }

    /**
     * @param  array<string, Programme>  $programmes
     * @return array<string, Cohort> keyed "RGN-2026"
     */
    private function cohorts(array $programmes): array
    {
        $out = [];

        foreach ($programmes as $code => $programme) {
            foreach ([2024 => 3, 2025 => 2, 2026 => 1] as $intake => $yearOfStudy) {
                $out["{$code}-{$intake}"] = Cohort::firstOrCreate(
                    ['programme_id' => $programme->id, 'intake_year' => $intake],
                    ['name' => "{$code} class of ".($intake + 3), 'current_year_of_study' => $yearOfStudy],
                );
            }
        }

        return $out;
    }

    /**
     * @param  array<string, Department>  $departments
     * @param  array<string, Programme>  $programmes
     * @return array<string, Course>
     */
    private function courses(array $departments, array $programmes): array
    {
        // code, title, department, credit hours (placeholder), programmes, year, semester
        $catalogue = [
            ['NUR 111', 'Foundations of Nursing', 'NUR', 3, ['RGN'], 1, 1],
            ['NUR 112', 'Anatomy and Physiology I', 'NUR', 3, ['RGN'], 1, 1],
            ['NUR 113', 'Communication Skills', 'NUR', 2, ['RGN'], 1, 2],
            ['NUR 211', 'Medical-Surgical Nursing I', 'NUR', 4, ['RGN'], 2, 1],
            ['NUR 212', 'Pharmacology', 'NUR', 3, ['RGN'], 2, 2],
            ['NUR 311', 'Community Health Nursing', 'NUR', 3, ['RGN'], 3, 1],
            ['MID 111', 'Foundations of Midwifery', 'MID', 3, ['RM'], 1, 1],
            ['MID 112', 'Reproductive Anatomy', 'MID', 3, ['RM'], 1, 1],
            ['MID 211', 'Antenatal Care', 'MID', 4, ['RM'], 2, 1],
            ['MID 212', 'Normal Labour', 'MID', 4, ['RM'], 2, 2],
            ['MID 311', 'Neonatal Care', 'MID', 3, ['RM'], 3, 1],
            ['SDV 101', 'Study Skills', 'SDV', 2, ['RGN', 'RM'], 1, 1],
        ];

        $out = [];

        foreach ($catalogue as [$code, $title, $dept, $credits, $programmeCodes, $yearOfStudy, $semester]) {
            $out[$code] = Course::firstOrCreate(['code' => $code], ['title' => $title, 'department_id' => $departments[$dept]->id, 'credit_hours' => $credits]);

            foreach ($programmeCodes as $programmeCode) {
                ProgrammeCourse::firstOrCreate(
                    ['programme_id' => $programmes[$programmeCode]->id, 'course_id' => $out[$code]->id, 'curriculum_version' => 'v1'],
                    ['year_of_study' => $yearOfStudy, 'semester_number' => $semester, 'is_required' => true],
                );
            }
        }

        return $out;
    }

    /**
     * The first semester's courses are on offer; the second semester's are not yet.
     *
     * @param  array<string, Course>  $courses
     * @return array<string, CourseOffering>
     */
    private function offerings(array $courses, Semester $semester): array
    {
        $firstSemester = ProgrammeCourse::query()->where('semester_number', 1)->pluck('course_id')->unique();
        $out = [];

        foreach ($courses as $code => $course) {
            if (! $firstSemester->contains($course->id)) {
                continue;
            }

            $out[$code] = CourseOffering::firstOrCreate(
                ['course_id' => $course->id, 'semester_id' => $semester->id],
                ['exam_date' => Carbon::parse('2026-12-14')->addDays(count($out) % 5)],
            );
        }

        return $out;
    }

    /**
     * 8 lecturers (the first is lecturer@demo.test) and a staff profile for the Head of Academics.
     *
     * @param  array<string, Department>  $departments
     * @return array<int, StaffProfile>
     */
    private function lecturers(array $departments): array
    {
        $head = User::where('email', 'head_of_academics@demo.test')->firstOrFail();
        StaffProfile::firstOrCreate(['user_id' => $head->id], ['staff_number' => 'STF-000', 'department_id' => null]);

        $out = [];

        foreach (range(1, 8) as $n) {
            $email = $n === 1 ? 'lecturer@demo.test' : "lecturer.{$n}@demo.test";
            $user = $n === 1
                ? User::where('email', $email)->firstOrFail()
                : $this->person($email, Role::Lecturer);

            $out[] = StaffProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['staff_number' => sprintf('STF-%03d', $n), 'department_id' => $departments[$n % 2 === 0 ? 'MID' : 'NUR']->id],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, CourseOffering>  $offerings
     * @param  array<int, StaffProfile>  $lecturers
     */
    private function assignLecturers(array $offerings, array $lecturers): void
    {
        $i = 0;

        foreach ($offerings as $offering) {
            LecturerAssignment::firstOrCreate([
                'course_offering_id' => $offering->id,
                'staff_profile_id' => $lecturers[$i % count($lecturers)]->id,
            ]);
            $i++;
        }
    }

    /**
     * One assessment per course for the demo: the whole mark ("Overall", weight 100).
     *
     * @param  array<string, CourseOffering>  $offerings
     */
    private function assessments(array $offerings): void
    {
        foreach ($offerings as $offering) {
            Assessment::firstOrCreate(['course_offering_id' => $offering->id, 'name' => 'Overall'], ['weight_percent' => 100, 'max_score' => 100]);
        }
    }

    /**
     * About 40 students across the six cohorts, enrolled in their programme's first-semester courses,
     * plus the demo student, who is deliberately not enrolled in anything.
     *
     * @param  array<string, Programme>  $programmes
     * @param  array<string, Cohort>  $cohorts
     * @param  array<string, CourseOffering>  $offerings
     */
    private function students(array $programmes, array $cohorts, array $offerings): void
    {
        $issue = app(IssueIndexNumber::class);
        $sizes = [7, 7, 7, 7, 6, 6];
        $serial = 0;
        $cohortList = array_values($cohorts);

        foreach ($cohortList as $index => $cohort) {
            $programme = $programmes[$cohort->programme->code];

            foreach (range(1, $sizes[$index]) as $unused) {
                $serial++;
                $user = $this->person(sprintf('student.%03d@demo.test', $serial), Role::Student);
                $profile = StudentProfile::firstOrNew(['user_id' => $user->id]);

                if (! $profile->exists) {
                    $profile->fill([
                        'index_number' => $issue->execute($programme, $cohort->intake_year),
                        'programme_id' => $programme->id,
                        'cohort_id' => $cohort->id,
                        'year_of_study' => $cohort->current_year_of_study,
                        'status' => StudentStatus::Active,
                    ])->save();
                }

                $this->enrol($profile, $offerings);
            }
        }

        // The demo student: a first-year nursing student with nothing registered yet.
        $demo = User::where('email', 'student@demo.test')->firstOrFail();
        $cohort = $cohorts['RGN-2026'];

        if (! StudentProfile::where('user_id', $demo->id)->exists()) {
            StudentProfile::create([
                'user_id' => $demo->id,
                'index_number' => $issue->execute($programmes['RGN'], $cohort->intake_year),
                'programme_id' => $programmes['RGN']->id,
                'cohort_id' => $cohort->id,
                'year_of_study' => 1,
                'status' => StudentStatus::Active,
            ]);
        }
    }

    /**
     * @param  array<string, CourseOffering>  $offerings
     */
    private function enrol(StudentProfile $student, array $offerings): void
    {
        $courseIds = ProgrammeCourse::query()
            ->where('programme_id', $student->programme_id)
            ->where('year_of_study', $student->year_of_study)
            ->where('semester_number', 1)
            ->pluck('course_id');

        foreach ($offerings as $offering) {
            if ($courseIds->contains($offering->course_id)) {
                Enrolment::firstOrCreate(
                    ['student_profile_id' => $student->id, 'course_offering_id' => $offering->id],
                    ['status' => EnrolmentStatus::Registered, 'registered_at' => now()],
                );
            }
        }
    }

    private function gradeScale(): void
    {
        $scale = GradeScale::firstOrCreate(
            ['name' => 'Demo scale (synthetic, not the College rules)'],
            ['is_active' => true, 'is_demo' => true, 'notes' => 'Placeholder bands and points for demonstrations only. The College has not confirmed its grading rules or GPA metric (D5). The points let a screen show a sample GPA labelled not official.'],
        );

        // Sample points (a plain 4.0 scale) so the frontend can show a GPA marked "sample, not official".
        foreach ([['A', 80, 100, 4.0], ['B', 70, 79.99, 3.0], ['C', 60, 69.99, 2.0], ['D', 50, 59.99, 1.0], ['F', 0, 49.99, 0.0]] as $sort => [$letter, $min, $max, $points]) {
            $scale->bands()->updateOrCreate(['letter' => $letter], ['min_score' => $min, 'max_score' => $max, 'points' => $points, 'sort' => $sort + 1]);
        }
    }

    private function registrationWindow(Semester $semester): void
    {
        RegistrationWindow::firstOrCreate(
            ['semester_id' => $semester->id],
            ['opens_at' => now()->subDays(14), 'closes_at' => now()->addDays(30), 'audience' => null],
        );
    }

    /** A synthetic person: a random Ghanaian-style name, demo password (config demo.password), active. */
    private function person(string $email, Role $role): User
    {
        $name = fake()->randomElement(self::GIVEN).' '.fake()->randomElement(self::FAMILY);
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $this->hash(), 'status' => 'active'],
        );

        // Re-running the seeder applies the current demo password to accounts that already exist.
        $user->forceFill(['password' => $this->hash(), 'email_verified_at' => now()])->save();
        $user->syncRoles([$role->value]);

        return $user;
    }

    private function hash(): string
    {
        // One hash reused for every demo account: hashing 50 passwords one by one is slow.
        return $this->passwordHash ??= Hash::make((string) config('demo.password'));
    }
}
