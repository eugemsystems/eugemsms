<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-015. Raised when a duty roster's
 * eligible-category filter (or the school's own staff) leaves no one
 * at all to assign — not when a single slot is short-staffed because
 * everyone eligible is on leave, which `GenerateDutyRosterAction`
 * just skips.
 */
class NoEligibleDutyStaffException extends DomainException
{
    public static function forRoster(int $rosterId): self
    {
        return new self(
            "Duty roster [{$rosterId}] has no eligible staff to assign at all.",
            ['roster_id' => $rosterId],
        );
    }

    public function errorCode(): string
    {
        return 'NO_ELIGIBLE_DUTY_STAFF';
    }
}
