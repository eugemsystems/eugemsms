<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ProcessExaminationResultsData
{
    public function __construct(
        public int $sessionId,
        public int $processedByUserId,
    ) {}
}
