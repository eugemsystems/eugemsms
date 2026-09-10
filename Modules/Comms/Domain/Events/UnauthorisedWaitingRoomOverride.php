<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

/**
 * Book I COM-07 §5 ⚠. Fired when a waiting-room override on a
 * learner-facing meeting is attempted without a named approver — the
 * attempt is always blocked (see `WaitingRoomOverrideRequiresApprovalException`),
 * this event exists purely so it is loggable/alertable.
 */
final class UnauthorisedWaitingRoomOverride
{
    public function __construct(
        public readonly int $meetingId,
        public readonly int $attemptedByUserId,
    ) {}
}
