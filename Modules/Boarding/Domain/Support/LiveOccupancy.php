<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

/**
 * Book F BRD-02 §5/Appendix A ⭐ — what `liveOccupancy()` hands back
 * to `BRD-04` catering and `OPS-06` muster. `allocated` is the
 * nominal figure (from `BRD-01` bed allocations); `present` is the
 * number that actually drives servings (AC-BRD-02-011).
 */
final readonly class LiveOccupancy
{
    public function __construct(
        public int $allocated,
        public int $present,
        public int $onExeat,
        public int $inSickBay,
        public int $missing,
        public bool $rollCallAvailable,
    ) {}
}
