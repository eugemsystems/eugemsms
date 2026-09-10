<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-06 §3/BR-COM-06-006. A ticket is raised as a real
 * `Modules\Finance\Models\AdHocCharge`, which — like every ad hoc
 * charge in this codebase — bills a specific student's fee account
 * (`ad_hoc_charges.student_id` is `NOT NULL`). An `external` attendee
 * with no linked student has no fee account for FIN-02 to charge; this
 * is a genuine, honest boundary (confirmed: no cash/at-the-door
 * ticketing mechanism exists in this codebase today), not an
 * oversight to paper over.
 */
final class TicketRequiresStudentAccountException extends DomainException
{
    public static function forAttendeeType(string $attendeeType): self
    {
        return new self(
            "A ticketed event requires a linked student fee account to charge; attendee type '{$attendeeType}' has none.",
            ['attendee_type' => $attendeeType],
        );
    }

    public function errorCode(): string
    {
        return 'TICKET_REQUIRES_STUDENT_ACCOUNT';
    }
}
