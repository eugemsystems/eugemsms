<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\SupplierContract;

final class ContractExpiring
{
    public function __construct(
        public readonly SupplierContract $contract,
        public readonly int $daysRemaining,
    ) {}
}
