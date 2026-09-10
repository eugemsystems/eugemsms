<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\Invoice;

final class InvoiceIssued
{
    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
