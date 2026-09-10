<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * Book B FIN-01 §6. The single write path for all money — every
 * financial event in the platform ultimately becomes one of these.
 */
final readonly class PostJournalData
{
    /**
     * @param  array<int, JournalLineData>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $journalType,
        public string $narration,
        public array $lines,
        public CarbonInterface $effectiveAt,
        public int $postedByUserId,
        public ?string $reference = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $batchUuid = null,
        public bool $overrideSoftClose = false,
        public bool $isReversal = false,
        public ?int $reversesJournalId = null,
        public ?string $reversalReason = null,
    ) {}
}
