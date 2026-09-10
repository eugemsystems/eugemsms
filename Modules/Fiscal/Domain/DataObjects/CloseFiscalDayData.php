<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects;

final readonly class CloseFiscalDayData
{
    public function __construct(
        public int $fiscalDayId,
        public int $closedByUserId,
    ) {}
}
