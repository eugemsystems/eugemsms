<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Carbon\CarbonInterface;

final readonly class UpdateTermData
{
    public function __construct(
        public int $termId,
        public int $schoolId,
        public int $academicYearId,
        public int $number,
        public string $name,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public ?int $actingUserId = null,
    ) {}
}
