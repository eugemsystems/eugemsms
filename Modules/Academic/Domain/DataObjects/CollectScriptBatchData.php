<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CollectScriptBatchData
{
    public function __construct(
        public int $paperId,
        public int $venueId,
        public int $scriptCount,
        public int $expectedCount,
        public int $collectedByStaffId,
        public int $recordedByUserId,
    ) {}
}
