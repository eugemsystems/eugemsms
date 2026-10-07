<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class MergeStudentsData
{
    public function __construct(
        public int $survivingStudentId,
        public int $mergedStudentId,
        public int $mergedByUserId,
        public ?string $reason = null,
    ) {}
}
