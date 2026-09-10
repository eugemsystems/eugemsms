<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Events;

final class OutbreakThresholdReached
{
    public function __construct(
        public readonly int $schoolId,
        public readonly string $presentingComplaint,
        public readonly int $caseCount,
    ) {}
}
