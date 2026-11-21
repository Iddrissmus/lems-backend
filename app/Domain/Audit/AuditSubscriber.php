<?php

namespace App\Domain\Audit;

use App\Domain\Academics\Events\EnrolmentCreated;
use App\Domain\Academics\Events\EnrolmentWithdrawn;
use App\Domain\Academics\Events\MarksSubmitted;
use App\Domain\Academics\Events\RegistrationWindowClosed;
use App\Domain\Academics\Events\RegistrationWindowCreated;
use App\Domain\Academics\Events\ResultsReleased;
use App\Domain\Academics\Events\StudentRegistered;
use App\Domain\Audit\Enums\AuditEvent;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Events\InvitationAccepted;
use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginLockedOut;
use App\Domain\Identity\Events\PasswordChanged;
use App\Domain\Identity\Events\UserInvited;
use App\Domain\Identity\Events\UserLoggedIn;
use App\Domain\Identity\Events\UserLoggedOut;
use App\Domain\Identity\Events\UserPasswordResetByAdmin;
use App\Domain\Identity\Events\UserRolesChanged;
use App\Domain\Identity\Events\UserStatusChanged;
use App\Domain\Identity\Events\UserUpdated;
use App\Models\Enrolment;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Turns domain events into audit entries.
 *
 * Deliberately synchronous (not queued): the actions dispatch their events
 * inside the same database transaction as the change itself, so the change
 * and its audit entry are saved together or not at all. A listener that
 * sends email or calls another service must NOT be added here; those
 * belong in their own queued listeners.
 */
class AuditSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(UserLoggedIn::class, $this->onLogin(...));
        $events->listen(LoginFailed::class, $this->onLoginFailed(...));
        $events->listen(LoginLockedOut::class, $this->onLockedOut(...));
        $events->listen(UserLoggedOut::class, $this->onLogout(...));
        $events->listen(PasswordChanged::class, $this->onPasswordChanged(...));
        $events->listen(PasswordReset::class, $this->onPasswordReset(...));
        $events->listen(InvitationAccepted::class, $this->onInvitationAccepted(...));
        $events->listen(UserInvited::class, $this->onInvited(...));
        $events->listen(UserUpdated::class, $this->onUpdated(...));
        $events->listen(UserRolesChanged::class, $this->onRolesChanged(...));
        $events->listen(UserStatusChanged::class, $this->onStatusChanged(...));
        $events->listen(RegistrationWindowCreated::class, $this->onRegistrationWindowCreated(...));
        $events->listen(RegistrationWindowClosed::class, $this->onRegistrationWindowClosed(...));
        $events->listen(StudentRegistered::class, $this->onStudentRegistered(...));
        $events->listen(EnrolmentCreated::class, $this->onEnrolmentCreated(...));
        $events->listen(EnrolmentWithdrawn::class, $this->onEnrolmentWithdrawn(...));
        $events->listen(UserPasswordResetByAdmin::class, $this->onPasswordResetByAdmin(...));
        $events->listen(MarksSubmitted::class, $this->onMarksSubmitted(...));
        $events->listen(ResultsReleased::class, $this->onResultsReleased(...));
    }

    /** Resolved per call (not injected) so it stays swappable and Octane-safe. */
    private function audit(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    private function onLogin(UserLoggedIn $e): void
    {
        $this->audit()->record(AuditEvent::LoginSucceeded, $e->user, $e->user);
    }

    private function onLoginFailed(LoginFailed $e): void
    {
        $this->audit()->record(AuditEvent::LoginFailed, after: ['email' => $e->email]);
    }

    private function onLockedOut(LoginLockedOut $e): void
    {
        $this->audit()->record(AuditEvent::LoginLockedOut, after: [
            'email' => $e->email,
            'locked_for_seconds' => $e->lockedForSeconds,
        ]);
    }

    private function onLogout(UserLoggedOut $e): void
    {
        $this->audit()->record(AuditEvent::Logout, $e->user, $e->user);
    }

    private function onPasswordChanged(PasswordChanged $e): void
    {
        // Never record the password itself, only the fact that it changed.
        $this->audit()->record(AuditEvent::PasswordChanged, $e->user, $e->user);
    }

    private function onPasswordReset(PasswordReset $e): void
    {
        /** @var User $user */
        $user = $e->user;

        $this->audit()->record(AuditEvent::PasswordReset, $user, $user);
    }

    private function onInvitationAccepted(InvitationAccepted $e): void
    {
        $this->audit()->record(AuditEvent::InvitationAccepted, $e->user, $e->user);
    }

    private function onInvited(UserInvited $e): void
    {
        $this->audit()->record(AuditEvent::UserInvited, $e->actor, $e->user, after: [
            'email' => $e->user->email,
            'roles' => $e->roles,
        ]);
    }

    private function onUpdated(UserUpdated $e): void
    {
        $this->audit()->record(AuditEvent::UserUpdated, $e->actor, $e->user, $e->before, $e->after);
    }

    private function onRolesChanged(UserRolesChanged $e): void
    {
        $this->audit()->record(AuditEvent::UserRolesChanged, $e->actor, $e->user, ['roles' => $e->before], ['roles' => $e->after]);
    }

    private function onRegistrationWindowCreated(RegistrationWindowCreated $e): void
    {
        $w = $e->window;

        $this->audit()->record(AuditEvent::RegistrationWindowCreated, $e->actor, $w, after: [
            'semester_id' => $w->semester->ulid,
            'opens_at' => $w->opens_at->toIso8601String(),
            'closes_at' => $w->closes_at->toIso8601String(),
            'audience' => array_map(fn (array $a) => ['programme' => $a['programme']['code'], 'year_of_study' => $a['year_of_study']], $w->audienceDetails()),
        ]);
    }

    private function onRegistrationWindowClosed(RegistrationWindowClosed $e): void
    {
        $this->audit()->record(AuditEvent::RegistrationWindowClosed, $e->actor, $e->window, ['closed_at' => null], ['closed_at' => $e->window->closed_at?->toIso8601String()]);
    }

    private function onStudentRegistered(StudentRegistered $e): void
    {
        $s = $e->student;

        // Facts only. The temporary password is never passed to the audit log.
        $this->audit()->record(AuditEvent::StudentRegistered, $e->actor, $s, after: [
            'index_number' => $s->index_number,
            'email' => $s->user->email,
            'programme' => $s->programme->code,
            'cohort' => $s->cohort->name,
            'year_of_study' => $s->year_of_study,
        ]);
    }

    private function onEnrolmentCreated(EnrolmentCreated $e): void
    {
        $this->audit()->record(AuditEvent::EnrolmentCreated, $e->actor, $e->enrolment, after: $this->enrolmentFacts($e->enrolment));
    }

    private function onEnrolmentWithdrawn(EnrolmentWithdrawn $e): void
    {
        $this->audit()->record(AuditEvent::EnrolmentWithdrawn, $e->actor, $e->enrolment, $this->enrolmentFacts($e->enrolment), ['status' => 'withdrawn']);
    }

    private function onMarksSubmitted(MarksSubmitted $e): void
    {
        // Facts only: which course, how many marks, which scale. Never the marks themselves.
        $this->audit()->record(AuditEvent::MarksSubmitted, $e->actor, $e->offering, after: [
            'course' => $e->offering->course->code,
            'marks_count' => $e->marksCount,
            'grade_scale' => $e->scale->name,
            'submitted_at' => $e->offering->marks_submitted_at?->toIso8601String(),
        ]);
    }

    private function onResultsReleased(ResultsReleased $e): void
    {
        $this->audit()->record(AuditEvent::ResultsReleased, $e->actor, $e->offering, ['released_at' => null], [
            'course' => $e->offering->course->code,
            'released_at' => $e->offering->results_released_at?->toIso8601String(),
        ]);
    }

    private function onPasswordResetByAdmin(UserPasswordResetByAdmin $e): void
    {
        // Only the fact that it happened; never the password.
        $this->audit()->record(AuditEvent::UserPasswordResetByAdmin, $e->actor, $e->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function enrolmentFacts(Enrolment $enrolment): array
    {
        $enrolment->loadMissing('student', 'offering.course');

        return [
            'student' => $enrolment->student->index_number,
            'course' => $enrolment->offering->course->code,
            'course_offering_id' => $enrolment->offering->ulid,
        ];
    }

    private function onStatusChanged(UserStatusChanged $e): void
    {
        $event = $e->after === UserStatus::Disabled ? AuditEvent::UserDisabled : AuditEvent::UserEnabled;

        $this->audit()->record($event, $e->actor, $e->user, ['status' => $e->before->value], ['status' => $e->after->value]);
    }
}
