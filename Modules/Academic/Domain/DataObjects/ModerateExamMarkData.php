<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ModerateExamMarkData
{
    public function __construct(
        public int $paperId,
        public int $candidateId,
        public int $moderatorStaffId,
        public float $moderatedMark,
    ) {}
}
