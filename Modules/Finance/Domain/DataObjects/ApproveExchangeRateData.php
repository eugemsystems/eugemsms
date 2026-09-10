<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ApproveExchangeRateData
{
    public function __construct(
        public int $exchangeRateId,
        public int $approvedByUserId,
    ) {}
}
