<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Modules\Comms\Models\EventAttendee;

/**
 * Book I COM-06 §3 ⭐ (AC-COM-06-004). `waitlisted` is the literal
 * "offers a waitlist instead of a silent failure" the acceptance
 * criterion asks for — the caller always gets a real row back, never
 * an exception, when the event is simply full.
 */
final readonly class RegisterForEventResult
{
    public function __construct(
        public EventAttendee $attendee,
        public bool $waitlisted,
        public ?int $waitlistPosition = null,
    ) {}
}
