<?php

namespace App\Domain\Identity\Events;

use App\Models\User;

/** An invited person set a password and activated their account. */
final class InvitationAccepted
{
    public function __construct(public readonly User $user) {}
}
