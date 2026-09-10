<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-07 §4 ⭐/BR-COM-07-003 (AC-COM-07-005).
 */
final class WaitingRoomOverrideRequiresApprovalException extends DomainException
{
    public static function forMeeting(int $meetingId): self
    {
        return new self(
            "Disabling the waiting room on learner-facing meeting #{$meetingId} requires an explicit, logged approval.",
            ['meeting_id' => $meetingId],
        );
    }

    public function errorCode(): string
    {
        return 'WAITING_ROOM_OVERRIDE_REQUIRES_APPROVAL';
    }
}
