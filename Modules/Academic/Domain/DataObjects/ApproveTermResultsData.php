<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ApproveTermResultsData
{
    public function __construct(
        public int $termId,
        public int $approvedByUserId,
        public ?int $classId = null,
    ) {}
}
