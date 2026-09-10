<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * Book B FIN-02 §4/BR-FIN-02-005 (AC-FIN-02-003/004). Built by
 * `RaiseMidTermSubjectChangeBillingListener` from `ACA-02`'s
 * `SubjectEnrolmentAdded`/`SubjectEnrolmentDropped` — see that class
 * and `BillMidTermSubjectChangeAction`'s docblocks.
 */
final readonly class BillMidTermSubjectChangeData
{
    public function __construct(
        public int $enrolmentId,
        public int $changeId,
        public int $performedByUserId,
    ) {}
}
