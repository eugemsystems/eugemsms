<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class GenerateStudentIdCardData
{
    public function __construct(
        public int $studentId,
        public int $generatedByUserId,
    ) {}
}
