<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use Carbon\CarbonInterface;
use Modules\Payroll\Domain\Exceptions\UnsupportedCalculationMethodException;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\StaffPayComponent;
use Modules\Payroll\Models\StaffPayStructure;

/**
 * Book H3 PPL-05 §2/§3. Resolves one staff member's active pay
 * components as of a pay date into the bases
 * `StatutoryCalculationEngine` needs — taxable gross, pensionable
 * gross, the ZIMDEF base, the NEC base — from the tax-treatment flags
 * on each `pay_components` row, never from a hard-coded list of
 * component codes (BR-PPL-05's own design rule, §0.1).
 *
 * Only `fixed` and `percentage_of_basic` calculation methods are
 * supported this pass — see `UnsupportedCalculationMethodException`.
 * The `basic` category component must use `fixed`, since every
 * `percentage_of_basic` component is resolved against it — the
 * resolver reads `basic` components first, in their own pass, purely
 * to establish that reference amount.
 */
final class PayComponentResolver
{
    public function resolve(StaffPayStructure $structure, CarbonInterface $asOf): ResolvedPayComponents
    {
        $components = StaffPayComponent::with('component')
            ->where('pay_structure_id', $structure->id)
            ->where('effective_from', '<=', $asOf->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf->toDateString()))
            ->get()
            ->filter(fn (StaffPayComponent $spc): bool => $spc->component->is_active);

        $basicMinor = $components
            ->filter(fn (StaffPayComponent $spc): bool => $spc->component->category === 'basic')
            ->sum(fn (StaffPayComponent $spc): int => $this->amountFor($spc, 0));

        $basicMinor = (int) $basicMinor;
        $allowancesMinor = 0;
        $overtimeMinor = 0;
        $bonusMinor = 0;
        $taxableGrossMinor = 0;
        $pensionableGrossMinor = 0;
        $zimdefBaseMinor = 0;
        $necBaseMinor = 0;
        $thirdPartyMinor = 0;
        $otherDeductionsMinor = 0;
        $lines = [];

        foreach ($components as $spc) {
            $component = $spc->component;
            $amount = $this->amountFor($spc, $basicMinor);

            if ($component->component_type === 'earning') {
                match ($component->category) {
                    'overtime' => $overtimeMinor += $amount,
                    'bonus' => $bonusMinor += $amount,
                    'basic' => null,
                    default => $allowancesMinor += $amount,
                };

                $taxableGrossMinor += $this->portion($amount, (string) $component->taxable_percent);
                $pensionableGrossMinor += $component->is_pensionable ? $amount : 0;
                $zimdefBaseMinor += $component->is_zimdef_applicable ? $amount : 0;
                $necBaseMinor += $component->is_nec_applicable ? $amount : 0;

                $lines[] = $this->line($component, $amount, 'earning');
            } else {
                if ($component->category === 'third_party') {
                    $thirdPartyMinor += $amount;
                } else {
                    $otherDeductionsMinor += $amount;
                }

                $lines[] = $this->line($component, $amount, 'deduction');
            }
        }

        $grossMinor = $basicMinor + $allowancesMinor + $overtimeMinor + $bonusMinor;

        return new ResolvedPayComponents(
            basicMinor: $basicMinor,
            allowancesMinor: $allowancesMinor,
            overtimeMinor: $overtimeMinor,
            bonusMinor: $bonusMinor,
            grossMinor: $grossMinor,
            taxableGrossMinor: $taxableGrossMinor,
            pensionableGrossMinor: $pensionableGrossMinor,
            zimdefBaseMinor: $zimdefBaseMinor,
            necBaseMinor: $necBaseMinor,
            thirdPartyMinor: $thirdPartyMinor,
            otherDeductionsMinor: $otherDeductionsMinor,
            lines: $lines,
        );
    }

    /**
     * @return array{component_id: int, component_type: string, description: string, amount_minor: int, is_taxable: bool}
     */
    private function line(PayComponent $component, int $amount, string $type): array
    {
        return [
            'component_id' => $component->id,
            'component_type' => $type,
            'description' => $component->name,
            'amount_minor' => $amount,
            'is_taxable' => $type === 'earning' && $component->is_taxable,
        ];
    }

    private function amountFor(StaffPayComponent $spc, int $basicMinor): int
    {
        return match ($spc->component->calculation_method) {
            'fixed' => $spc->amount_minor ?? 0,
            'percentage_of_basic' => (int) bcdiv(bcmul((string) $basicMinor, Bc::numeric((string) ($spc->percent ?? $spc->component->default_percent))), '100', 0),
            default => throw UnsupportedCalculationMethodException::forMethod($spc->component->calculation_method, $spc->component->code),
        };
    }

    private function portion(int $amountMinor, string $percent): int
    {
        return (int) bcdiv(bcmul((string) $amountMinor, Bc::numeric($percent)), '100', 0);
    }
}
