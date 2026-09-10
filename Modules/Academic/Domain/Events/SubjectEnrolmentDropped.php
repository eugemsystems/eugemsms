<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Models\LearnerSubjectEnrolment;
use Modules\Academic\Models\SubjectEnrolmentChange;

/**
 * Book D ACA-02 §4/§9. `FIN-02`'s `RaiseMidTermSubjectChangeBillingListener`
 * binds to this to raise the pro-rata credit note for the unused
 * remainder; see `DropSubjectAction`.
 */
final class SubjectEnrolmentDropped
{
    public function __construct(
        public readonly LearnerSubjectEnrolment $enrolment,
        public readonly SubjectEnrolmentChange $change,
    ) {}
}
