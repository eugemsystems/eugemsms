<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class StartAttemptData
{
    public function __construct(
        public int $testId,
        public int $studentId,
    ) {}
}
