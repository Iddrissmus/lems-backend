<?php

namespace App\Domain\Academics\Enums;

/**
 * Where a course's marks are in their life. Worked out from the offering's own
 * dates and its scores, never stored, so it cannot drift.
 */
enum OfferingMarkStatus: string
{
    /** No marks saved yet. */
    case NotStarted = 'not_started';

    /** Some marks saved; the lecturer may still change them. */
    case Draft = 'draft';

    /** Submitted and locked; students cannot see it yet. */
    case Submitted = 'submitted';

    /** Released by the Head of Academics; students can see letter grades. */
    case Released = 'released';
}
