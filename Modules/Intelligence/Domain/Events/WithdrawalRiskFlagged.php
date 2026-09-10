<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Events;

use Modules\Intelligence\Models\WithdrawalRiskFlag;

final class WithdrawalRiskFlagged
{
    public function __construct(
        public readonly WithdrawalRiskFlag $flag,
    ) {}
}
