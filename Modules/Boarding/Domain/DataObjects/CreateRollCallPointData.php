<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateRollCallPointData
{
    /**
     * @param  array<int, string>  $appliesOnDays
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $scheduledTime,
        public array $appliesOnDays,
        public ?int $hostelId = null,
        public bool $appliesInTermOnly = true,
        public int $graceMinutes = 10,
        public bool $isMandatory = true,
        public ?int $escalationProfileId = null,
    ) {}
}
