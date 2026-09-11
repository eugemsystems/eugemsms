<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SubmitAttemptData
{
    public function __construct(
        public int $attemptId,
        public bool $autoSubmitted = false,
    ) {}
}
