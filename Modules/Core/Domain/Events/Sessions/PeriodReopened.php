<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use Modules\Core\Domain\Support\PeriodType;
use Modules\Core\Models\PeriodReopenRequest;
use Modules\Core\Models\Term;

final class PeriodReopened
{
    public function __construct(
        public readonly Term $term,
        public readonly PeriodType $periodType,
        public readonly PeriodReopenRequest $request,
    ) {}
}
