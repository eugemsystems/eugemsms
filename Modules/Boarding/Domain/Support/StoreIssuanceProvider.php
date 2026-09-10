<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

/**
 * Book F BRD-04 §0.3/§4 — the dependency this book names precisely.
 * `FIN-09` (Book H) is not built in this pass, so this codebase runs
 * in the spec's own sanctioned fallback: "planning-only mode" — menus,
 * recipes, per-capita scaling and dietary management all work;
 * costing and stock availability report as unknown rather than zero
 * or false. `null` from either method means "unavailable", never a
 * number/boolean standing in for missing data.
 */
interface StoreIssuanceProvider
{
    public function currentCostMinor(int $schoolId, int $inventoryItemId): ?int;

    /**
     * @return bool|null null = unknown (no store to check against)
     */
    public function checkAvailability(int $schoolId, int $inventoryItemId, float $requiredQuantity): ?bool;
}
