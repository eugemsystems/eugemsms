<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Sessions;

use Modules\Core\Models\Term;

/**
 * Book A CORE-03 §2 — what `ACT-TakePeriodSnapshot` actually captures:
 * "trial balance, learner balances, key registers" (`payload`) and
 * "per-table counts at snapshot time" (`row_counts`). Both come from
 * modules not built yet (Book B Finance, Book D People) —
 * `NullSnapshotPayloadProvider` returns an honestly-empty snapshot until
 * one binds a real implementation. The hash-chaining mechanism itself
 * (BR-CORE-03-015) works correctly either way: an empty payload still
 * hashes and chains, it just isn't forensically useful yet.
 */
interface SnapshotPayloadProvider
{
    /**
     * @return array<string, mixed>
     */
    public function payload(Term $term): array;

    /**
     * @return array<string, int>
     */
    public function rowCounts(Term $term): array;
}
