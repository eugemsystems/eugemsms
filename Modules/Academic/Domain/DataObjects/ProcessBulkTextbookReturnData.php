<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ProcessBulkTextbookReturnData
{
    /**
     * @param  array<int, int>  $itemIds
     * @param  array<int, array<int, int>>  $returnedItemIdsByStudent  studentId => item_ids actually handed back
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $classId,
        public array $itemIds,
        public int $feeComponentId,
        public int $chargedByUserId,
        public array $returnedItemIdsByStudent = [],
    ) {}
}
