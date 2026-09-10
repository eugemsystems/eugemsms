<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Contracts\Sessions;

use Modules\Core\Domain\Support\Money;
use Modules\Core\Models\Term;

/**
 * BR-CORE-03-019 — the roll-over invariant: Σ(closing balances of term N)
 * ≡ Σ(opening balances of term N+1), per currency. Real balances live in
 * `journal_lines`, the general ledger (Book B, not built yet) —
 * `NullTermBalanceProvider` reports no balances at all until Book B
 * binds a real implementation, which makes the invariant check
 * vacuously (and correctly) pass rather than fail on data that doesn't
 * exist yet. `Money`, not a bare decimal string — this compares exactly
 * the same way every other monetary comparison in the platform does
 * (ADR-006, BR-GLOBAL-020: integer minor units, never float).
 */
interface TermBalanceProvider
{
    /**
     * @return array<string, Money> keyed by ISO currency code
     */
    public function closingBalances(Term $term): array;

    /**
     * @return array<string, Money> keyed by ISO currency code
     */
    public function openingBalances(Term $term): array;
}
