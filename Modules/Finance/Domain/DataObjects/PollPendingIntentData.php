<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class PollPendingIntentData
{
    public function __construct(
        public int $paymentIntentId,
        public int $processedByUserId,
    ) {}
}
