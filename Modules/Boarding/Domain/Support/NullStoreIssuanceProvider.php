<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Support;

/**
 * Book F BRD-04 §0.3/§4. The planning-only fallback's null
 * implementation — bound by default until `FIN-09` ships a real one.
 * "Never display an uncosted meal as costing nothing" (§4's own
 * wording) is why both methods return `null`, not `0`/`false`.
 */
final class NullStoreIssuanceProvider implements StoreIssuanceProvider
{
    public function currentCostMinor(int $schoolId, int $inventoryItemId): ?int
    {
        return null;
    }

    public function checkAvailability(int $schoolId, int $inventoryItemId, float $requiredQuantity): ?bool
    {
        return null;
    }
}
