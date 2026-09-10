<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class AmendMarkData
{
    public function __construct(
        public int $assessmentId,
        public int $studentId,
        public int $changedByUserId,
        public string $changeReason,
        public ?float $rawMark = null,
        public bool $isAbsent = false,
        public bool $approved = false,
    ) {}
}
