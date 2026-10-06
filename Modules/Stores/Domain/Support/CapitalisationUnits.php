<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Support;

/**
 * Splits a received/issued quantity into the asset records it becomes: one
 * asset per whole unit at the unit cost (each is tagged and verified on its
 * own), or a single asset at the full cost when the quantity is not a whole
 * number of units.
 */
final class CapitalisationUnits
{
    /**
     * @return list<int> acquisition cost in minor units, one entry per asset
     */
    public static function costs(float $quantity, int $unitCostMinor): array
    {
        if ($quantity <= 0.0 || $unitCostMinor <= 0) {
            return [];
        }

        if (abs($quantity - round($quantity)) < 0.0001) {
            return array_fill(0, (int) round($quantity), $unitCostMinor);
        }

        return [(int) round($quantity * $unitCostMinor)];
    }
}
