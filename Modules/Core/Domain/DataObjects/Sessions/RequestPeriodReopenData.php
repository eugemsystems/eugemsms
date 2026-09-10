<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Modules\Core\Domain\Support\PeriodType;

final readonly class RequestPeriodReopenData
{
    public function __construct(
        public int $termId,
        public PeriodType $periodType,
        public string $reason,
        public int $requestedByUserId,
    ) {}
}
