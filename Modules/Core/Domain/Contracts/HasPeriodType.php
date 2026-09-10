<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts;

use Modules\Core\Domain\Support\PeriodType;

/**
 * Implemented by a session-bound model that is governed by the academic
 * period track rather than the default financial one — e.g. a mark or a
 * report card, which can be locked while the term's finances are still
 * soft-closed (Volume 1 Part 4.3). `BelongsToSession` defaults to
 * `PeriodType::Financial` for any model that does not implement this.
 */
interface HasPeriodType
{
    public function periodType(): PeriodType;
}
