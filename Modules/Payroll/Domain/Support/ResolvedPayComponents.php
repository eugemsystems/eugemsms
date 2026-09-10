<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

final readonly class ResolvedPayComponents
{
    /**
     * @param  array<int, array{component_id: int, component_type: string, description: string, amount_minor: int, is_taxable: bool}>  $lines
     */
    public function __construct(
        public int $basicMinor,
        public int $allowancesMinor,
        public int $overtimeMinor,
        public int $bonusMinor,
        public int $grossMinor,
        public int $taxableGrossMinor,
        public int $pensionableGrossMinor,
        public int $zimdefBaseMinor,
        public int $necBaseMinor,
        public int $thirdPartyMinor,
        public int $otherDeductionsMinor,
        public array $lines,
    ) {}
}
