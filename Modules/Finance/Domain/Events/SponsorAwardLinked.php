<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\DiscountAward;

/**
 * Book K FIN-07 §7 — "(→ PPL-03)".
 */
final class SponsorAwardLinked
{
    public function __construct(
        public readonly DiscountAward $award,
    ) {}
}
