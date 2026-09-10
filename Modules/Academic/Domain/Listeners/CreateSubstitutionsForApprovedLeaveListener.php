<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Listeners;

use Modules\Academic\Domain\Actions\CreateSubstitutionsForLeaveAction;
use Modules\Academic\Domain\DataObjects\CreateSubstitutionsForLeaveData;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Models\School;
use Modules\People\Domain\Events\LeaveApproved;
use Throwable;

/**
 * Book E ACA-03 §6/BR-ACA-03-016. Closes the wiring
 * `Modules\People\Domain\Events\LeaveApproved`'s own docblock named
 * as pending ("consumed by ACA-03... neither listens yet"). Lives in
 * Academic (the consumer) listening to People's event — the same
 * direction as Finance's `RaiseMidTermSubjectChangeBillingListener`
 * (Book D ACA-02), registered in `AcademicServiceProvider::boot()`.
 *
 * Failure here never blocks the leave approval itself — a
 * substitution creation problem is a scheduling concern, not a
 * reason to undo an HR decision already made.
 */
final class CreateSubstitutionsForApprovedLeaveListener
{
    public function __construct(private readonly CreateSubstitutionsForLeaveAction $createSubstitutions) {}

    public function handle(LeaveApproved $event): void
    {
        $leave = $event->leaveRequest;

        if (SchoolContext::current()?->id !== $leave->school_id) {
            SchoolContext::set(School::findOrFail($leave->school_id));
        }

        try {
            $this->createSubstitutions->execute(new CreateSubstitutionsForLeaveData(
                staffId: $leave->staff_id,
                fromDate: $leave->starts_on,
                toDate: $leave->ends_on,
                leaveRequestId: $leave->id,
                reason: 'leave',
            ));
        } catch (Throwable) {
            // Scheduling fallout from a leave approval is surfaced on
            // the deputy head's cover report (BR-ACA-03-018), not by
            // failing the approval that already happened.
        }
    }
}
