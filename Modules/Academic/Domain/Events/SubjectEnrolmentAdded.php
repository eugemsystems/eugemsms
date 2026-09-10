<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\SubjectEnrolmentChange;

/**
 * Book D ACA-02 §4/§9 ⭐. `FIN-02`'s `RaiseMidTermSubjectChangeBillingListener`
 * binds to this to raise the per-subject charge (step 7 onward of the
 * add/drop billing flow); see `EnrolSubjectAction`.
 */
final class SubjectEnrolmentAdded
{
    public function __construct(
        public readonly LearnerSubjectEnrolment $enrolment,
        public readonly SubjectEnrolmentChange $change,
    ) {}
}
