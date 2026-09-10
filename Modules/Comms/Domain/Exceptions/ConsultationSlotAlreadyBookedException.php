<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-07 §4 ⭐/BR-COM-07-005 (AC-COM-07-004). Carries the next
 * available slot in its context — "the other is offered the next
 * available slot", not silently retried or auto-booked into it.
 */
final class ConsultationSlotAlreadyBookedException extends DomainException
{
    public static function forSlot(int $windowId, CarbonInterface $slotStartsAt, ?CarbonInterface $nextAvailable): self
    {
        return new self(
            'This consultation slot was just taken by another booking.',
            ['window_id' => $windowId, 'requested_slot' => $slotStartsAt->toIso8601String(), 'next_available' => $nextAvailable?->toIso8601String()],
        );
    }

    public function errorCode(): string
    {
        return 'CONSULTATION_SLOT_ALREADY_BOOKED';
    }
}
