<?php

namespace App\Domain\Academics\Events;

use App\Models\RegistrationWindow;
use App\Models\User;

/** A registration window was opened up for a semester. The audit log listens. */
final class RegistrationWindowCreated
{
    public function __construct(
        public readonly RegistrationWindow $window,
        public readonly ?User $actor,
    ) {}
}
