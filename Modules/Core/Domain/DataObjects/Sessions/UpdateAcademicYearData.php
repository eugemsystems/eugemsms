<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Carbon\CarbonInterface;

final readonly class UpdateAcademicYearData
{
    public function __construct(
        public int $yearId,
        public int $schoolId,
        public string $name,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public bool $isCurrent,
        public ?int $actingUserId = null,
    ) {}
}
