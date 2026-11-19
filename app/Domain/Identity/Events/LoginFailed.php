<?php

namespace App\Domain\Identity\Events;

/** A login attempt failed. Only the email typed is kept, never the password. */
final class LoginFailed
{
    public function __construct(public readonly string $email) {}
}
