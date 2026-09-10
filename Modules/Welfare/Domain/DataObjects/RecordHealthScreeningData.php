<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordHealthScreeningData
{
    /**
     * @param  array<string, mixed>|null  $results
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $studentId,
        public string $screeningType,
        public CarbonInterface $screenedOn,
        public string $outcome,
        public string $screenedBy,
        public ?array $results = null,
    ) {}
}
