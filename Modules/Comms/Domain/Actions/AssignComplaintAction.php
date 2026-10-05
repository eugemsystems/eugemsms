<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-AssignComplaint (Book I COM-08 §3, BR-COM-08-004). Nothing in the
 * backend pass ever assigned a complaint, and the SLA check only runs
 * against an assigned one — so without this an overdue complaint alerts
 * nobody. Records a `reassignment` update that is internal
 * (`visible_to_raiser = false`): who handles it is not the raiser's
 * business. A complaint routed to safeguarding is never assigned here;
 * it belongs to the BRD-08 case, not the ordinary queue.
 */
final class AssignComplaintAction extends Action
{
    public function __construct(
        private readonly AddComplaintUpdateAction $addUpdate,
    ) {}

    public function execute(int $complaintId, int $staffId, int $assignedByUserId): Complaint
    {
        return $this->transaction(function () use ($complaintId, $staffId, $assignedByUserId): Complaint {
            $complaint = Complaint::findOrFail($complaintId);

            if ($complaint->isRoutedToSafeguarding()) {
                throw new InvalidStateTransitionException(
                    'A complaint routed to safeguarding is handled in its safeguarding case, not assigned from the complaint queue.',
                    ['complaint_id' => $complaintId],
                );
            }

            $complaint->update([
                'assigned_to_staff_id' => $staffId,
                'status' => $complaint->status === 'received' ? 'acknowledged' : $complaint->status,
            ]);

            $this->addUpdate->execute($complaint->id, 'reassignment', $assignedByUserId, 'Complaint assigned.', visibleToRaiser: false);

            return $complaint;
        });
    }
}
