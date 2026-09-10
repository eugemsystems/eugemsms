<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-06 §2/BR-COM-06-006. `fee_component_id` is how a ticket
 * charge knows which income/debtor account it posts to — the same
 * requirement `CreateAdHocChargeAction` itself enforces via a
 * `NOT NULL` FK.
 */
final class TicketingRequiresFeeComponentException extends DomainException
{
    public static function forCalendarEvent(int $calendarEventId): self
    {
        return new self(
            "A ticketed registration for calendar event #{$calendarEventId} requires a fee_component_id to charge against.",
            ['calendar_event_id' => $calendarEventId],
        );
    }

    public function errorCode(): string
    {
        return 'TICKETING_REQUIRES_FEE_COMPONENT';
    }
}
