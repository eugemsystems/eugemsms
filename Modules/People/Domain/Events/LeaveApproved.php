<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\LeaveRequest;

/**
 * Consumed by `ACA-03` for substitution cover and `PPL-05` for unpaid
 * leave in payroll, once either exists — neither listens yet.
 */
final class LeaveApproved
{
    public function __construct(
        public readonly LeaveRequest $leaveRequest,
    ) {}
}
