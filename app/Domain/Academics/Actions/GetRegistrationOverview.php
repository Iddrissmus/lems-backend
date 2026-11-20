<?php

namespace App\Domain\Academics\Actions;

use App\Domain\Academics\Enums\EnrolmentStatus;
use App\Models\Enrolment;
use App\Models\RegistrationWindow;
use App\Models\Semester;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Where a student stands with registration in the current semester.
 */
class GetRegistrationOverview
{
    /**
     * @return array{semester: Semester|null, window: RegistrationWindow|null, registered: Collection<int, Enrolment>}
     */
    public function execute(StudentProfile $student): array
    {
        $semester = Semester::with('academicYear')->where('is_current', true)->first();

        if ($semester === null) {
            return ['semester' => null, 'window' => null, 'registered' => collect()];
        }

        $registered = Enrolment::query()
            ->with('offering.course')
            ->where('student_profile_id', $student->id)
            ->where('status', EnrolmentStatus::Registered->value)
            ->whereHas('offering', fn ($q) => $q->where('semester_id', $semester->id))
            ->orderBy('registered_at')
            ->orderBy('id')
            ->get();

        return ['semester' => $semester, 'window' => $this->relevantWindow($student, $semester), 'registered' => $registered];
    }

    /**
     * The window that matters to this student: one that is open, else the next to
     * open, else the one that closed most recently; null if none covers them.
     */
    private function relevantWindow(StudentProfile $student, Semester $semester): ?RegistrationWindow
    {
        $windows = RegistrationWindow::where('semester_id', $semester->id)->get()
            ->filter(fn (RegistrationWindow $w) => $w->coversStudent($student));

        return $windows->first(fn (RegistrationWindow $w) => $w->status() === RegistrationWindow::OPEN)
            ?? $windows->filter(fn (RegistrationWindow $w) => $w->status() === RegistrationWindow::NOT_YET_OPEN)->sortBy('opens_at')->first()
            ?? $windows->sortByDesc('closes_at')->first();
    }
}
