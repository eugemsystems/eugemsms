<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class VoidInvoiceData
{
    public function __construct(
        public int $invoiceId,
        public string $reason,
        public int $voidedByUserId,
        public ?int $replacementInvoiceId = null,
    ) {}
}
