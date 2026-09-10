<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

/**
 * Book C PPL-03 §4. One party's share of one billed line —
 * `liabilityId` is null when the share was assigned by the pass-3
 * residual fallback rather than an explicit `fee_liabilities` row.
 */
final readonly class LiabilityShare
{
    public function __construct(
        public int $guardianId,
        public int $lineId,
        public int $componentId,
        public int $shareMinor,
        public string $currency,
        public ?int $liabilityId,
    ) {}
}
