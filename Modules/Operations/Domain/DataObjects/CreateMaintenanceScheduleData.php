<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateMaintenanceScheduleData
{
    /**
     * @param  array<int, string>  $taskChecklist
     */
    public function __construct(
        public int $schoolId,
        public int $maintenanceAssetId,
        public string $name,
        public string $triggerType,
        public string $assignedTeam,
        public array $taskChecklist,
        public ?int $intervalDays = null,
        public ?float $intervalUnits = null,
        public int $leadTimeDays = 7,
        public ?float $estimatedHours = null,
        public ?CarbonInterface $nextDueOn = null,
        public ?float $nextDueUnits = null,
    ) {}
}
