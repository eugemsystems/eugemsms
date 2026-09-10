<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Carbon\CarbonInterface;

final readonly class CreateYearData
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public CarbonInterface $startsOn,
        public CarbonInterface $endsOn,
        public ?int $actingUserId = null,
        public bool $generateThreeTerms = false,
    ) {}
}
