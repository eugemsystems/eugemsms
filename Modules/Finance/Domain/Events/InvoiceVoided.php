<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\Invoice;

final class InvoiceVoided
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly Invoice $replacement,
    ) {}
}
