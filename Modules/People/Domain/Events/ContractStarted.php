<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\StaffContract;

final class ContractStarted
{
    public function __construct(
        public readonly StaffContract $contract,
    ) {}
}
