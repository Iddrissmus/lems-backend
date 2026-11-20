<?php

namespace App\Domain\Identity\Enums;

/**
 * The roles built so far. The rest of the scope's list (clinical_coordinator,
 * external_assessor, stores_officer, librarian, src_officer) are added when
 * their modules are built and Schedule 1 confirms the group list (D10).
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case HeadOfAcademics = 'head_of_academics';
    case AcademicAffairs = 'academic_affairs';
    case IctAdmin = 'ict_admin';
    case Registrar = 'registrar';
    case Bursar = 'bursar';
    case Lecturer = 'lecturer';
    case Student = 'student';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    /**
     * Permissions this role holds (super_admin is handled by a Gate::before
     * bypass and so lists none). Other roles receive permissions in the
     * module that needs them, once the college confirms the rules.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            // ict_admin has the same account-management rights as head_of_academics
            // (owner's call, 2026-11-20). The academic permissions are proposals
            // from docs/api/demo-contract.md section 2, pending the frontend's answer.
            self::HeadOfAcademics => [...Permission::usersGroup(), Permission::StudentsView, Permission::RegistrationManage],
            self::IctAdmin => [...Permission::usersGroup(), Permission::AccountsCreate, Permission::AccountsResetPassword, Permission::StudentsView],
            self::Registrar => [Permission::AccountsCreate, Permission::StudentsView],
            self::AcademicAffairs => [Permission::StudentsView],
            self::Student => [Permission::RegistrationSelf],
            default => [],
        };
    }
}
