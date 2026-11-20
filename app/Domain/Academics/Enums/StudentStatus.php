<?php

namespace App\Domain\Academics\Enums;

/**
 * Only `active` exists for now. The other statuses and the rules for moving
 * between them (BE-011) come from the College; none are invented here.
 */
enum StudentStatus: string
{
    case Active = 'active';
}
