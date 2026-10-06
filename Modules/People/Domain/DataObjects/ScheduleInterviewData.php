<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ScheduleInterviewData
{
    /**
     * @param  array<int, int>  $panelUserIds
     */
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public CarbonInterface $scheduledAt,
        public array $panelUserIds,
        public ?string $venue = null,
    ) {}
}
