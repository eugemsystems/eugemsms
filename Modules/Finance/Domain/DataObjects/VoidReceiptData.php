<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class VoidReceiptData
{
    public function __construct(
        public int $receiptId,
        public string $reason,
        public int $voidedByUserId,
    ) {}
}
