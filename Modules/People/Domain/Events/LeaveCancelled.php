<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\LeaveRequest;

final class LeaveCancelled
{
    public function __construct(
        public readonly LeaveRequest $leaveRequest,
    ) {}
}
