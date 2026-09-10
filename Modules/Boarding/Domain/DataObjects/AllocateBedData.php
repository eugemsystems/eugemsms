<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AllocateBedData
{
    /**
     * @param  array<int, int>  $candidateHostelIds  preferred hostels, in order; empty = any hostel of the learner's gender
     */
    public function __construct(
        public int $studentId,
        public int $academicYearId,
        public int $termId,
        public CarbonInterface $effectiveFrom,
        public int $allocatedByUserId,
        public array $candidateHostelIds = [],
        public string $allocationType = 'initial',
        public ?string $reason = null,
        public bool $asDraft = true,
    ) {}
}
