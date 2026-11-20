<?php

namespace App\Domain\Academics\Events;

use App\Models\RegistrationWindow;
use App\Models\User;

/** A registration window was closed early by staff. The audit log listens. */
final class RegistrationWindowClosed
{
    public function __construct(
        public readonly RegistrationWindow $window,
        public readonly ?User $actor,
    ) {}
}
