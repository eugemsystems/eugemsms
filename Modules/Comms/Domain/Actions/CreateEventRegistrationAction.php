<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Comms\Domain\Exceptions\TicketingRequiresFeeComponentException;
use Modules\Comms\Models\CalendarEvent;
use Modules\Comms\Models\EventRegistration;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateEventRegistration (Book I COM-06 §2/BR-COM-06-006/007).
 */
final class CreateEventRegistrationAction extends Action
{
    public function execute(
        int $schoolId,
        int $calendarEventId,
        ?int $capacity = null,
        bool $requiresTicket = false,
        ?int $ticketPriceMinor = null,
        ?string $ticketCurrency = null,
        ?int $feeComponentId = null,
        ?Carbon $rsvpDeadline = null,
    ): EventRegistration {
        CalendarEvent::findOrFail($calendarEventId);

        return $this->transaction(function () use (
            $schoolId, $calendarEventId, $capacity, $requiresTicket,
            $ticketPriceMinor, $ticketCurrency, $feeComponentId, $rsvpDeadline,
        ): EventRegistration {
            if ($requiresTicket && $feeComponentId === null) {
                throw TicketingRequiresFeeComponentException::forCalendarEvent($calendarEventId);
            }

            return EventRegistration::create([
                'school_id' => $schoolId,
                'calendar_event_id' => $calendarEventId,
                'capacity' => $capacity,
                'requires_ticket' => $requiresTicket,
                'ticket_price_minor' => $ticketPriceMinor,
                'ticket_currency' => $ticketCurrency,
                'fee_component_id' => $feeComponentId,
                'rsvp_deadline' => $rsvpDeadline,
                'registered_count' => 0,
            ]);
        });
    }
}
