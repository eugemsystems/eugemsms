<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DataObjects;

/**
 * Book J INT-03 §3 ⭐/BR-INT-03-001. What one indicator's resolver
 * returns for one subject — `severityPercent` (0-100, how much of
 * this indicator's own maximum applies right now), the plain-language
 * sentence a pastoral reviewer reads directly, and the source record
 * it traces to. A resolver returns `null` instead when the indicator
 * genuinely doesn't apply (no data yet, or nothing adverse to report)
 * — it never fabricates a zero-severity factor just to fill the list.
 */
final readonly class RiskIndicatorResult
{
    public function __construct(
        public float $severityPercent,
        public string $plainLanguage,
        public string $source,
    ) {}
}
