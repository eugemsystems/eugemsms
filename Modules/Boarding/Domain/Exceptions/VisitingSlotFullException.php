<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book F BRD-03 §5/BR-BRD-03-022.
 */
class VisitingSlotFullException extends DomainException
{
    public static function forSlot(int $visitingDayId, string $slotStartsAt): self
    {
        return new self(
            "Visiting day #{$visitingDayId}'s slot at {$slotStartsAt} is already full.",
            ['visiting_day_id' => $visitingDayId, 'slot_starts_at' => $slotStartsAt],
        );
    }

    public function errorCode(): string
    {
        return 'VISITING_SLOT_FULL';
    }
}
