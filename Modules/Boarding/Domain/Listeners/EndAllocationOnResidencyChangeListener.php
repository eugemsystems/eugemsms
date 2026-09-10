<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Listeners;

use Modules\Boarding\Domain\Actions\EndBedAllocationAction;
use Modules\Boarding\Domain\DataObjects\EndBedAllocationData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\People\Domain\Events\LearnerResidencyChanged;
use Throwable;

/**
 * Book F BRD-01 §4/BR-BRD-01-005/AC-BRD-01-003. A residency change to
 * anything other than `BOARDER`/`WEEKLY_BOARDER` ends the learner's
 * bed allocation on the change's own effective date and releases the
 * bed. Listens to `Modules\People\Domain\Events\LearnerResidencyChanged`
 * (Book C PPL-01), the same cross-module direction as `ACA-06`'s
 * subject-enrolment listeners. Failure here never blocks the
 * residency change itself.
 */
final class EndAllocationOnResidencyChangeListener
{
    public function __construct(private readonly EndBedAllocationAction $endBedAllocation) {}

    public function handle(LearnerResidencyChanged $event): void
    {
        $student = $event->student;

        if (in_array($event->change->new_value, ['BOARDER', 'WEEKLY_BOARDER'], true)) {
            return;
        }

        if (SchoolContext::current()?->id !== $student->school_id) {
            SchoolContext::set(School::findOrFail($student->school_id));
        }

        try {
            $this->endBedAllocation->execute(new EndBedAllocationData(
                studentId: $student->id,
                effectiveTo: $event->change->effective_from,
                reason: 'Residency changed to '.$event->change->new_value,
            ));
        } catch (Throwable) {
            // A boarding-side allocation problem is surfaced on the
            // housemaster's occupancy board, not by failing the
            // residency change that already happened.
        }
    }
}
