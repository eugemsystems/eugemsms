<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use Modules\Core\Models\PeriodRollover;

final class RolloverStarted
{
    public function __construct(
        public readonly PeriodRollover $rollover,
    ) {}
}
