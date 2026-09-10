<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class RecordSpecialArrangementData
{
    /**
     * @param  array<int, int>|null  $appliesToPapers
     */
    public function __construct(
        public int $schoolId,
        public int $sessionId,
        public int $studentId,
        public string $arrangementType,
        public string $justification,
        public ?int $extraTimePercent = null,
        public ?int $supportingDocumentId = null,
        public ?array $appliesToPapers = null,
    ) {}
}
