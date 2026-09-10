<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class EnterThirdExamMarkData
{
    public function __construct(
        public int $paperId,
        public int $candidateId,
        public int $markerStaffId,
        public float $mark,
    ) {}
}
