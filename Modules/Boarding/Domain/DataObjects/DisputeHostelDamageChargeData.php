<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class DisputeHostelDamageChargeData
{
    public function __construct(
        public int $damageId,
        public string $disputeReason,
        public int $disputedByUserId,
    ) {}
}
