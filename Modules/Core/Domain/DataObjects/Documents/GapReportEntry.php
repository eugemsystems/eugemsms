<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Documents;

use Carbon\CarbonInterface;

final readonly class GapReportEntry
{
    public function __construct(
        public string $formattedNumber,
        public int $sequence,
        public string $reason,
        public CarbonInterface $voidedAt,
    ) {}
}
