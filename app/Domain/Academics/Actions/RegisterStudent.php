<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\StudentStatus;
use App\Domain\Academics\Events\StudentRegistered;
use App\Domain\Identity\Enums\Role;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\Cohort;
use App\Models\Programme;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Security\TemporaryPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff create a student: the portal account, the student record and the index
 * number in one step, optionally registering the first courses too.
 *
 * The account gets a generated temporary password that the student must change
 * at first sign-in (ADR 0009). It is returned to the caller ONCE and is never
 * written to the audit log or kept anywhere in readable form.
 */
class RegisterStudent
{
    public function __construct(
        private readonly IssueIndexNumber $indexNumbers,
        private readonly RegisterForCourses $registerForCourses,
    ) {}

    /**
     * @param  array{name: string, email: string, programme_id: string, cohort_id?: string|null, year_of_study?: int|null, moh_membership_id?: string|null, course_offering_ids?: array<int, string>|null}  $data
     * @return array{0: StudentProfile, 1: string} the student and the temporary password
     */
    public function execute(array $data, User $actor): array
    {
        $programme = Programme::where('ulid', $data['programme_id'])->first()
            ?? throw ValidationException::withMessages(['programme_id' => ['This programme does not exist.']]);

        $cohort = $this->cohort($programme, $data['cohort_id'] ?? null, $data['year_of_study'] ?? null);
        $password = TemporaryPassword::generate();

        $student = DB::transaction(function () use ($data, $programme, $cohort, $password, $actor): StudentProfile {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'status' => UserStatus::Active,
            ]);
            $user->forceFill(['must_change_password' => true])->save();
            $user->assignRole(Role::Student->value);

            $student = StudentProfile::create([
                'user_id' => $user->id,
                'index_number' => $this->indexNumbers->execute($programme, $cohort->intake_year),
                'moh_membership_id' => $data['moh_membership_id'] ?? null,
                'programme_id' => $programme->id,
                'cohort_id' => $cohort->id,
                'year_of_study' => $data['year_of_study'] ?? $cohort->current_year_of_study,
                'status' => StudentStatus::Active,
            ]);
            $student->setRelation('user', $user);
            $student->setRelation('programme', $programme);
            $student->setRelation('cohort', $cohort);

            event(new StudentRegistered($student, $actor));

            // On the student's behalf, so no registration window is needed. Any refusal
            // rolls the whole creation back.
            if (($data['course_offering_ids'] ?? []) !== []) {
                $this->registerForCourses->execute($student, $data['course_offering_ids'], $actor, enforceWindow: false);
            }

            return $student;
        });

        return [$student, $password];
    }

    /**
     * Uses the cohort given, or finds the one cohort of the programme that is in the given year.
     */
    private function cohort(Programme $programme, ?string $cohortId, ?int $year): Cohort
    {
        if ($cohortId !== null) {
            $cohort = Cohort::where('ulid', $cohortId)->first()
                ?? throw ValidationException::withMessages(['cohort_id' => ['This cohort does not exist.']]);

            if ($cohort->programme_id !== $programme->id) {
                throw ValidationException::withMessages(['cohort_id' => ['This cohort does not belong to the chosen programme.']]);
            }

            return $cohort;
        }

        $matches = $year === null ? collect() : Cohort::where('programme_id', $programme->id)->where('current_year_of_study', $year)->get();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages(['cohort_id' => ['Choose a cohort: give cohort_id, or a year_of_study that matches exactly one cohort of this programme.']]);
        }

        /** @var Cohort */
        return $matches->first();
    }
}
