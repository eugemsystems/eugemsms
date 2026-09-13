<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class RecordPaymentPlanInstalmentPaymentData
{
    public function __construct(
        public int $instalmentId,
        public int $paidMinor,
    ) {}
}
