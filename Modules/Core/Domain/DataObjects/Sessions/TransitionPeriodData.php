<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Modules\Core\Domain\Support\PeriodState;
use Modules\Core\Domain\Support\PeriodType;

final readonly class TransitionPeriodData
{
    public function __construct(
        public int $termId,
        public PeriodType $periodType,
        public PeriodState $toState,
        public int $performedByUserId,
        public ?string $reason = null,
        public ?int $approvedByUserId = null,
        public ?string $ipAddress = null,
    ) {}
}
