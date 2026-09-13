<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CalculateAccountTreeBalancesData
{
    public function __construct(
        public int $schoolId,
    ) {}
}
