<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * BR-FIN-02-011. Creates version n+1 of an existing structure — the
 * caller supplies the complete new rule/item set (not a diff).
 */
final readonly class ReviseFeeStructureData
{
    /**
     * @param  array<int, array{attribute: string, operator: string, value: mixed, custom_field_key?: string|null}>  $rules
     * @param  array<int, array{component_id: int, billing_basis: string, currency: string, amount_minor?: int|null, unit_rate_minor?: int|null, unit_label?: string|null, minimum_minor?: int|null, maximum_minor?: int|null, tier_bands?: array<int, array{from: int, to?: int|null, rate?: int, multiplier?: float}>|null, subject_rate_map?: array<string, int>|null, is_prorated?: bool, proration_basis?: string, charge_frequency?: string, is_optional?: bool}>  $items
     */
    public function __construct(
        public int $structureId,
        public array $rules,
        public array $items,
        public int $revisedByUserId,
    ) {}
}
