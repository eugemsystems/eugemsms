<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateProviderMeetingData
{
    public function __construct(
        public string $topic,
        public CarbonInterface $startsAt,
        public int $durationMinutes,
        public bool $waitingRoomEnabled,
    ) {}
}
