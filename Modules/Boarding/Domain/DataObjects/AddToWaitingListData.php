<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class AddToWaitingListData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public ?int $preferredHostelId = null,
        public ?float $priorityScore = null,
        public ?string $reason = null,
    ) {}
}
