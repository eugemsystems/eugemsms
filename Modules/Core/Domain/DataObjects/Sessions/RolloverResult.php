<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Modules\Core\Domain\Support\RolloverStatus;
use Modules\Core\Models\PeriodRollover;

final readonly class RolloverResult
{
    /**
     * @param  array<int, array<string, mixed>>  $stepLog
     */
    public function __construct(
        public PeriodRollover $rollover,
        public RolloverStatus $status,
        public array $stepLog,
    ) {}
}
