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
