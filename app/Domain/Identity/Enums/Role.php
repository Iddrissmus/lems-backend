<?php

namespace App\Domain\Identity\Enums;

/**
 * The roles from the project brief. More (admissions_officer, ict_admin)
 * are added when their modules are built.
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case HeadOfAcademics = 'head_of_academics';
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
            self::HeadOfAcademics => Permission::usersGroup(),
            default => [],
        };
    }
}
