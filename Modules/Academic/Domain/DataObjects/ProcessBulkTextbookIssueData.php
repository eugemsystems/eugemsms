<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ProcessBulkTextbookIssueData
{
    /**
     * @param  array<int, int>  $itemIds
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $classId,
        public array $itemIds,
        public int $issuedByUserId,
    ) {}
}
