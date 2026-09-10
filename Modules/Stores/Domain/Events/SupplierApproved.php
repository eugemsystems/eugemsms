<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\Supplier;

final class SupplierApproved
{
    public function __construct(
        public readonly Supplier $supplier,
    ) {}
}
