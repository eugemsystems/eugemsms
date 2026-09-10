<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class EnterMarkData
{
    public function __construct(
        public int $assessmentId,
        public int $studentId,
        public int $enteredByUserId,
        public ?float $rawMark = null,
        public bool $isAbsent = false,
        public ?string $absenceReason = null,
        public ?string $comment = null,
    ) {}
}
