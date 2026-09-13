<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class CancelPaymentPlanData
{
    public function __construct(
        public int $paymentPlanId,
    ) {}
}
