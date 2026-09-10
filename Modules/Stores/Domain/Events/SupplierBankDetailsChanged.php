<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\Supplier;

final class SupplierBankDetailsChanged
{
    public function __construct(
        public readonly Supplier $supplier,
        public readonly int $requestedByUserId,
        public readonly int $approvedByUserId,
    ) {}
}
