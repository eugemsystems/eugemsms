<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

/**
 * Book J INT-02 §3 ⭐/BR-INT-02-003 (AC-INT-02-001). `status` is
 * exactly the "colour-coded green/amber/red — never a bare number
 * with no context" the rule asks for.
 */
final readonly class KpiValueResult
{
    public function __construct(
        public string $key,
        public string $label,
        public string $unit,
        public float $currentValue,
        public ?float $targetValue,
        public float $warningThresholdPercent,
        public string $status,
        public bool $higherIsBetter,
    ) {}
}
