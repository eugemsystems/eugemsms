<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\SupplierTaxClearance;

final class TaxClearanceExpiring
{
    public function __construct(
        public readonly SupplierTaxClearance $clearance,
        public readonly int $daysRemaining,
    ) {}
}
