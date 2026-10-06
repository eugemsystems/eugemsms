<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ScheduleEntranceExamData
{
    /**
     * @param  array<int, array<string, mixed>>  $papers  [{subject, max_mark, weight}]
     */
    public function __construct(
        public int $schoolId,
        public int $intakeId,
        public string $name,
        public CarbonInterface $examDate,
        public string $startTime,
        public array $papers,
        public ?string $venue = null,
        public ?int $capacity = null,
        public ?float $passMarkPercent = null,
    ) {}
}
