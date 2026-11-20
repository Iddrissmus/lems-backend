<?php

namespace App\Exceptions;

/**
 * A write to marks that were already submitted (HTTP 423, code grade_locked).
 * There is no override for lecturers or tutors; only an authorised correction
 * creates a new version.
 */
class GradeLocked extends ApiException
{
    public function __construct()
    {
        parent::__construct(423, 'grade_locked', 'These marks have been submitted and are locked. Only an authorised correction can change them.');
    }
}
