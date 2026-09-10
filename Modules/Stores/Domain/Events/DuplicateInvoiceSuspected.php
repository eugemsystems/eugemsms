<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\SupplierInvoice;

final class DuplicateInvoiceSuspected
{
    public function __construct(
        public readonly SupplierInvoice $invoice,
        public readonly string $reason,
    ) {}
}
