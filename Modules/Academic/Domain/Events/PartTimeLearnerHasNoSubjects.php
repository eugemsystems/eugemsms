<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Events;

use Modules\People\Models\Student;

/**
 * Book D ACA-02 §9 ⚠/BR-ACA-02-011 (AC-ACA-02-008). Fired instead of
 * throwing when a `PART_TIME` learner drops to zero billable subjects
 * — the drop itself succeeds (the spec is explicit that no silent
 * zero-value invoice is issued, not that the drop is refused); a
 * billing exception report listens for this once one exists.
 */
final class PartTimeLearnerHasNoSubjects
{
    public function __construct(
        public readonly Student $student,
    ) {}
}
