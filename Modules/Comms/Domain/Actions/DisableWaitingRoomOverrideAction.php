<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Modules\Comms\Domain\Events\UnauthorisedWaitingRoomOverride;
use Modules\Comms\Domain\Events\WaitingRoomOverrideDisabled;
use Modules\Comms\Domain\Exceptions\WaitingRoomOverrideRequiresApprovalException;
use Modules\Comms\Models\ScheduledMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-DisableWaitingRoomOverride (Book I COM-07 §4 ⭐/BR-COM-07-003
 * (AC-COM-07-005)). `$approvedByUserId` is not optional — the caller
 * (a controller/Livewire component gated on a real permission, per
 * this codebase's own convention that Actions never re-check
 * permissions themselves) must name a human approver. A missing
 * approver is refused AND logged via `UnauthorisedWaitingRoomOverride`
 * — the named event the spec itself lists for exactly this case.
 */
final class DisableWaitingRoomOverrideAction extends Action
{
    public function execute(int $meetingId, int $attemptedByUserId, ?int $approvedByUserId): ScheduledMeeting
    {
        $meeting = ScheduledMeeting::findOrFail($meetingId);

        if ($approvedByUserId === null) {
            event(new UnauthorisedWaitingRoomOverride($meetingId, $attemptedByUserId));

            throw WaitingRoomOverrideRequiresApprovalException::forMeeting($meetingId);
        }

        return $this->transaction(function () use ($meeting, $approvedByUserId): ScheduledMeeting {
            $meeting->update(['waiting_room_enabled' => false]);

            event(new WaitingRoomOverrideDisabled($meeting, $approvedByUserId));

            return $meeting;
        });
    }
}
