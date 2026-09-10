<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use Modules\Core\Models\PeriodRollover;

final class RolloverFailed
{
    public function __construct(
        public readonly PeriodRollover $rollover,
        public readonly string $reason,
    ) {}
}
