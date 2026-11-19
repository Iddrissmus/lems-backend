<?php

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    /** Account created by an admin; the person has not set a password yet. */
    case Invited = 'invited';
    case Active = 'active';
    case Disabled = 'disabled';

    public function canLogin(): bool
    {
        return $this === self::Active;
    }
}
