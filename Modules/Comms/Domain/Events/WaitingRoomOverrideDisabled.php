<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\ScheduledMeeting;

final class WaitingRoomOverrideDisabled
{
    public function __construct(
        public readonly ScheduledMeeting $meeting,
        public readonly int $approvedByUserId,
    ) {}
}
