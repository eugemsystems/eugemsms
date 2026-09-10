<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book C PPL-04 §4/BR-PPL-04-010 (AC-PPL-04-003). Requests exceeding
 * the available balance are refused unless the approver holds
 * `staff.approve_leave_overdraft`.
 */
class LeaveBalanceExceededException extends DomainException
{
    public static function forRequest(int $staffId, string $requestedDays, string $availableDays): self
    {
        return new self(
            "Staff member [{$staffId}] requested {$requestedDays} days but has only {$availableDays} available.",
            ['staff_id' => $staffId, 'requested_days' => $requestedDays, 'available_days' => $availableDays],
        );
    }

    public function errorCode(): string
    {
        return 'LEAVE_BALANCE_EXCEEDED';
    }
}
