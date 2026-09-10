<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RegisterSchoolCurrencyData
{
    public function __construct(
        public int $schoolId,
        public string $currency,
        public bool $isBase = false,
        public bool $isAcceptedForPayment = true,
        public int $roundingIncrementMinor = 1,
    ) {}
}
