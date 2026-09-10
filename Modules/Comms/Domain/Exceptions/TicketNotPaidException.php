<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-06 §3 ⭐/BR-COM-06-006 (AC-COM-06-003).
 */
final class TicketNotPaidException extends DomainException
{
    public static function forAttendee(int $attendeeId): self
    {
        return new self(
            "Attendee #{$attendeeId} has not paid for a ticket this event requires; check-in is refused.",
            ['attendee_id' => $attendeeId],
        );
    }

    public function errorCode(): string
    {
        return 'TICKET_NOT_PAID';
    }
}
