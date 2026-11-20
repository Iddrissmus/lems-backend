<?php

namespace App\Domain\Academics\Enums;

enum EnrolmentStatus: string
{
    case Registered = 'registered';
    case Withdrawn = 'withdrawn';
}
