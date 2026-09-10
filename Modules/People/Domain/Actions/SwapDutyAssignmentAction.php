<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\SwapDutyAssignmentData;
use Modules\People\Domain\Events\DutySwapped;
use Modules\People\Domain\Exceptions\DutySwapConsentRequiredException;
use Modules\People\Models\DutyAssignment;

/**
 * ACT-SwapDutyAssignment (Book C PPL-04 §4/BR-PPL-04-016 ⭐). The
 * original assignment is never deleted or overwritten — it's marked
 * `swapped` and a new row is created for the staff member taking it
 * over, so "both original and swapped assignments remain visible"
 * holds literally, not just as an audit trail.
 */
final class SwapDutyAssignmentAction extends Action
{
    public function execute(SwapDutyAssignmentData $data): DutyAssignment
    {
        $original = DutyAssignment::findOrFail($data->assignmentId);

        if ($original->status !== 'assigned') {
            throw new InvalidStateTransitionException(
                "Only an [assigned] duty can be swapped; this one is [{$original->status}].",
                ['status' => $original->status],
            );
        }

        if (! $data->bothPartiesConsented) {
            throw DutySwapConsentRequiredException::forAssignment($original->id);
        }

        return $this->transaction(function () use ($original, $data): DutyAssignment {
            $original->update(['status' => 'swapped', 'swapped_with_staff_id' => $data->newStaffId, 'swap_approved_by' => $data->approvedByUserId]);

            $replacement = DutyAssignment::create([
                'school_id' => $original->school_id,
                'roster_id' => $original->roster_id,
                'staff_id' => $data->newStaffId,
                'starts_at' => $original->starts_at,
                'ends_at' => $original->ends_at,
                'status' => 'assigned',
                'notes' => "Swapped from staff [{$original->staff_id}]",
            ]);

            event(new DutySwapped($original, $replacement));

            return $replacement;
        });
    }
}
