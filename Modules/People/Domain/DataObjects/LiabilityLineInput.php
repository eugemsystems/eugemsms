<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

/**
 * Book C PPL-03 §4. What `LiabilityResolver` needs from one billed
 * line — deliberately not a `LearnerFeeLine` reference, so People
 * never depends on Finance's models. `Finance\...\IssueInvoicesForAssignmentAction`
 * maps its own fee lines into these before calling the resolver.
 */
final readonly class LiabilityLineInput
{
    public function __construct(
        public int $lineId,
        public int $componentId,
        public int $netMinor,
        public string $currency,
    ) {}
}
