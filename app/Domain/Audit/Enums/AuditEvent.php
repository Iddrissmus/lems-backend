<?php

namespace App\Domain\Audit\Enums;

/**
 * Every kind of thing the audit log records. The value is stored in the
 * database and shown to the frontend, so never rename one; add a new case.
 */
enum AuditEvent: string
{
    // Authentication
    case LoginSucceeded = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case LoginLockedOut = 'auth.login_locked';
    case Logout = 'auth.logout';
    case PasswordChanged = 'auth.password_changed';
    case PasswordReset = 'auth.password_reset';
    case InvitationAccepted = 'auth.invitation_accepted';

    // User management
    case UserInvited = 'user.invited';
    case UserUpdated = 'user.updated';
    case UserRolesChanged = 'user.roles_changed';
    case UserDisabled = 'user.disabled';
    case UserEnabled = 'user.enabled';
    case UserPasswordResetByAdmin = 'user.password_reset_by_admin';

    // Registration
    case RegistrationWindowCreated = 'registration_window.created';
    case RegistrationWindowClosed = 'registration_window.closed';
    case StudentRegistered = 'student.registered';
    case EnrolmentCreated = 'enrolment.created';
    case EnrolmentWithdrawn = 'enrolment.withdrawn';
}
