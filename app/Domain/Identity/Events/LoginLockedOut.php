<?php

namespace App\Domain\Identity\Events;

/** Too many failed attempts: this email is now locked for a while. */
final class LoginLockedOut
{
    public function __construct(public readonly string $email, public readonly int $lockedForSeconds) {}
}
