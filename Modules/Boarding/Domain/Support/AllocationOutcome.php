<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

use Modules\Boarding\Models\HostelBed;

/**
 * Book F BRD-01 §3. What `BedAllocationEngine::findBedFor()` hands
 * back — either a chosen bed with its soft violations named, or no
 * bed at all with the reason every candidate was hard-blocked.
 */
final readonly class AllocationOutcome
{
    /**
     * @param  array<int, string>  $softViolations
     */
    public function __construct(
        public int $studentId,
        public ?HostelBed $bed,
        public array $softViolations = [],
        public ?string $blockingReason = null,
    ) {}

    public function isPlaced(): bool
    {
        return $this->bed !== null;
    }
}
