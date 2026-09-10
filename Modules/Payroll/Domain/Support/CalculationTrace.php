<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Support;

use Modules\Core\Domain\Support\Money;

/**
 * Book H3 PPL-05 §3 ⭐/BR-PPL-05-017. The full derivation of one
 * payslip — which band table, each band applied, the NSSA ceiling
 * and whether it bit, the AIDS Levy base — stored verbatim into
 * `payslips.calculation_trace` so a bursar can answer "why is this
 * deduction this amount" by reading the screen, not by re-deriving
 * the arithmetic.
 */
final class CalculationTrace
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $steps = [];

    public function note(string $message): void
    {
        $this->steps[] = ['type' => 'note', 'message' => $message];
    }

    public function nssa(Money $pensionable, Money $ceiling, Money $insurable, string $employeeRate, Money $employeeAmount): void
    {
        $this->steps[] = [
            'type' => 'nssa_pension',
            'pensionable_gross' => $pensionable->jsonSerialize(),
            'ceiling' => $ceiling->jsonSerialize(),
            'ceiling_applied' => $insurable->compareTo($pensionable) < 0,
            'insurable_earnings' => $insurable->jsonSerialize(),
            'employee_rate' => $employeeRate,
            'employee_amount' => $employeeAmount->jsonSerialize(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $bandsApplied
     */
    public function paye(Money $base, array $bandsApplied, Money $total): void
    {
        $this->steps[] = [
            'type' => 'paye',
            'base' => $base->jsonSerialize(),
            'bands_applied' => $bandsApplied,
            'total' => $total->jsonSerialize(),
        ];
    }

    public function aidsLevy(Money $payeDue, string $rate, Money $amount): void
    {
        $this->steps[] = [
            'type' => 'aids_levy',
            'paye_due' => $payeDue->jsonSerialize(),
            'rate' => $rate,
            'amount' => $amount->jsonSerialize(),
            'note' => 'Computed on PAYE due, not on gross pay.',
        ];
    }

    public function apwcs(Money $insurableWageBill, string $rate, Money $amount): void
    {
        $this->steps[] = [
            'type' => 'apwcs',
            'insurable_wage_bill' => $insurableWageBill->jsonSerialize(),
            'rate' => $rate,
            'amount' => $amount->jsonSerialize(),
            'note' => 'Employer-only, uncapped — no employee deduction.',
        ];
    }

    public function zimdef(Money $base, Money $amount): void
    {
        $this->steps[] = ['type' => 'zimdef', 'base' => $base->jsonSerialize(), 'amount' => $amount->jsonSerialize()];
    }

    public function nec(Money $base, string $employeeRate, string $employerRate, Money $employeeAmount, Money $employerAmount): void
    {
        $this->steps[] = [
            'type' => 'nec',
            'base' => $base->jsonSerialize(),
            'employee_rate' => $employeeRate,
            'employer_rate' => $employerRate,
            'employee_amount' => $employeeAmount->jsonSerialize(),
            'employer_amount' => $employerAmount->jsonSerialize(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return $this->steps;
    }
}
