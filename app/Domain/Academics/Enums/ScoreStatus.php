<?php

namespace App\Domain\Academics\Enums;

enum ScoreStatus: string
{
    /** The lecturer may still change it. */
    case Draft = 'draft';

    /** Locked. Nothing can change or delete it; a correction is a new version. */
    case Submitted = 'submitted';
}
