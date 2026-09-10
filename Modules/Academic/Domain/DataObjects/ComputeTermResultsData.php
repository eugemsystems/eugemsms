<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ComputeTermResultsData
{
    public function __construct(
        public int $studentId,
        public int $termId,
    ) {}
}
