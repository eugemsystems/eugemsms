<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Events;

use Illuminate\Support\Carbon;
use Modules\Farm\Models\Livestock;

final class WithdrawalPeriodBlocked
{
    public function __construct(
        public readonly Livestock $livestock,
        public readonly Carbon $withdrawalEndsOn,
    ) {}
}
