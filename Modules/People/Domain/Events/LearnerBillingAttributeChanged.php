<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Student;
use Modules\People\Models\StudentAttributeChange;

/**
 * Book C PPL-01 §12/Appendix A. Base shape for every billing-attribute
 * event `ACT-ChangeBillingAttribute` emits — one concrete subclass per
 * attribute, so `FIN-02` (and other listeners) can bind to the specific
 * change they care about rather than filtering a generic event by name.
 */
abstract class LearnerBillingAttributeChanged
{
    public function __construct(
        public readonly Student $student,
        public readonly StudentAttributeChange $change,
    ) {}
}
