<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Audit;

/**
 * BR-CORE-08-008/AC-CORE-08-002. Raised (alongside a critical
 * `SecurityEvent`) the moment the nightly chain verification finds a
 * broken link — the vendor is alerted immediately, not on the next
 * digest.
 */
final class FinancialAuditChainBroken
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $brokenAtSequence,
    ) {}
}
