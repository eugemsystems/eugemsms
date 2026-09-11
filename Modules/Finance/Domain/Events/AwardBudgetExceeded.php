<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\DiscountAward;
use Modules\Finance\Models\SchemeBudgetEnvelope;

/**
 * Book K FIN-07 §4/§7/BR-FIN-07-009 ⭐ (AC-FIN-07-003).
 */
final class AwardBudgetExceeded
{
    public function __construct(
        public readonly DiscountAward $award,
        public readonly SchemeBudgetEnvelope $envelope,
        public readonly int $shortfallMinor,
    ) {}
}
