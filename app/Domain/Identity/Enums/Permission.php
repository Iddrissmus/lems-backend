<?php

namespace App\Domain\Identity\Enums;

/**
 * Every permission the API checks. Names are "<area>.<action>".
 * Each module adds its own cases here when it is built.
 */
enum Permission: string
{
    case UsersView = 'users.view';
    case UsersInvite = 'users.invite';
    case UsersUpdate = 'users.update';
    case UsersDisable = 'users.disable';
    case UsersAssignRoles = 'users.assign-roles';
    case AuditView = 'audit.view';

    // Academic modules. These use the colon style the frontend's Roles and
    // permissions screen already shows (docs/api/demo-contract.md section 2).
    // The users.* and audit.view names above are renamed to match once the
    // frontend engineer confirms the vocabulary.
    case AccountsCreate = 'accounts:create';
    case AccountsResetPassword = 'accounts:reset-password';
    case StudentsView = 'students:view';
    case RegistrationManage = 'registration:manage';
    case RegistrationSelf = 'registration:self';

    // Marks, release and results (docs/api/demo-contract.md sections 7 to 9).
    case CoursesTeach = 'courses:teach';
    case MarksEnter = 'marks:enter';
    case MarksSubmit = 'marks:submit';
    case AcademicsDashboardView = 'academics:dashboard:view';
    case ResultsRelease = 'results:release';
    case ResultsViewOwn = 'results:view-own';

    /**
     * @return array<int, self>
     */
    public static function usersGroup(): array
    {
        return [
            self::UsersView,
            self::UsersInvite,
            self::UsersUpdate,
            self::UsersDisable,
            self::UsersAssignRoles,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission) => $permission->value, self::cases());
    }
}
