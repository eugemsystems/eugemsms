<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Models\Complaint;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ChangeComplaintStatus (Book I COM-08 §3). Moves an open complaint
 * through acknowledged / investigating / escalated, or closes it. A
 * resolution (with its written outcome) goes through
 * `ResolveComplaintAction`, never here. Each change is recorded as a
 * `status_change` update the raiser can see — a visible status trail is
 * what BR-COM-08-003 promises them. Refused for a complaint already
 * resolved/closed and for one routed to safeguarding.
 */
final class ChangeComplaintStatusAction extends Action
{
    private const array ALLOWED = ['acknowledged', 'investigating', 'escalated', 'closed'];

    public function __construct(
        private readonly AddComplaintUpdateAction $addUpdate,
    ) {}

    public function execute(int $complaintId, string $status, int $changedByUserId): Complaint
    {
        if (! in_array($status, self::ALLOWED, true)) {
            throw new InvalidStateTransitionException(
                "A complaint status can be set to acknowledged, investigating, escalated or closed — got '{$status}'.",
                ['status' => $status],
            );
        }

        return $this->transaction(function () use ($complaintId, $status, $changedByUserId): Complaint {
            $complaint = Complaint::findOrFail($complaintId);

            if ($complaint->isRoutedToSafeguarding() || in_array($complaint->status, ['resolved', 'closed'], true)) {
                throw new InvalidStateTransitionException(
                    "Complaint #{$complaintId} is routed to safeguarding or already finished and cannot change status here.",
                    ['complaint_id' => $complaintId, 'status' => $complaint->status],
                );
            }

            $complaint->update(['status' => $status, 'closed_at' => $status === 'closed' ? now() : null]);

            $this->addUpdate->execute($complaint->id, 'status_change', $changedByUserId, "Status changed to {$status}.", visibleToRaiser: true);

            return $complaint;
        });
    }
}
