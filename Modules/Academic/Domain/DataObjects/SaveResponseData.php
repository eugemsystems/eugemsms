<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class SaveResponseData
{
    public function __construct(
        public int $attemptId,
        public int $questionId,
        public mixed $responseValue,
        public bool $isFlaggedByCandidate = false,
    ) {}
}
