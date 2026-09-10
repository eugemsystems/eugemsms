<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Events;

use Modules\Fiscal\Models\FiscalReceipt;

final class ReceiptRejected
{
    public function __construct(
        public readonly FiscalReceipt $fiscalReceipt,
    ) {}
}
