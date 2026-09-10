<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class DropSubjectData
{
    public function __construct(
        public int $studentId,
        public int $subjectId,
        public int $termId,
        public int $droppedByUserId,
        public ?CarbonInterface $effectiveTo = null,
        public ?string $dropReason = null,
    ) {}
}
