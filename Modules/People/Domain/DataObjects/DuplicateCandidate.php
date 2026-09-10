<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class DuplicateCandidate
{
    public function __construct(
        public int $studentId,
        public string $admissionNumber,
        public string $matchedOn,
    ) {}
}
