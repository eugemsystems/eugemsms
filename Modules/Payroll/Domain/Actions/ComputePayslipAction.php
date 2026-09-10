<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Domain\Support\Money;
use Modules\Payroll\Domain\DataObjects\ComputePayslipData;
use Modules\Payroll\Domain\Exceptions\PayrollComputationException;
use Modules\Payroll\Domain\Support\Bc;
use Modules\Payroll\Domain\Support\CalculationTrace;
use Modules\Payroll\Domain\Support\PayComponentResolver;
use Modules\Payroll\Domain\Support\PayeBandCalculator;
use Modules\Payroll\Domain\Support\StatutoryConfigResolver;
use Modules\Payroll\Domain\Support\UnpaidLeaveProrationCalculator;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\PayslipLine;
use Modules\Payroll\Models\StaffLoan;
use Modules\Payroll\Models\StaffPayStructure;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffContract;

/**
 * ACT-ComputePayslip (Book H3 PPL-05 §3 ⭐, following the spec's own
 * pseudocode order exactly). Called once per staff member by
 * `ComputePayrollRunAction`, which catches `PayrollComputationException`
 * and records it on the run's exception report — this Action itself
 * always either returns a persisted `Payslip` or throws.
 *
 * Deferred against the full spec: deductible tax credits
 * (`$this->deductibleCredits($d->staff)` in the spec's pseudocode),
 * split-currency salary blending (BR-PPL-05-010 — a structure with a
 * `usd_portion_percent`/`zwg_portion_percent` split is not yet
 * supported; only `payment_currency` as a single currency is), and
 * `benefit_in_kind` non-cash earnings are all out of scope for this
 * pass.
 */
final class ComputePayslipAction extends Action
{
    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly PayComponentResolver $componentResolver,
        private readonly UnpaidLeaveProrationCalculator $proration,
        private readonly StatutoryConfigResolver $statutoryConfig,
        private readonly PayeBandCalculator $payeCalculator,
    ) {}

    public function execute(ComputePayslipData $data): Payslip
    {
        $run = PayrollRun::findOrFail($data->payrollRunId);
        $staff = Staff::findOrFail($data->staffId);

        $this->assertEligible($staff, $data->payDate);

        $structure = $this->activeStructure($staff->id, $data->payDate);

        if ($structure === null) {
            throw PayrollComputationException::missingPayStructure($staff->id);
        }

        $this->assertContractNotExpired($staff->id, $structure, $data->payDate);

        $currency = Currency::from($structure->payment_currency);
        $trace = new CalculationTrace;

        $resolved = $this->componentResolver->resolve($structure, $data->payDate);
        $prorationResult = $this->proration->calculate($staff->id, $data->periodStart, $data->periodEnd);
        $factor = $prorationResult['factor'];

        $gross = Money::of($resolved->grossMinor, $currency);
        $taxable = Money::of($resolved->taxableGrossMinor, $currency);
        $pensionable = Money::of($resolved->pensionableGrossMinor, $currency);
        $zimdefBase = Money::of($resolved->zimdefBaseMinor, $currency);
        $necBase = Money::of($resolved->necBaseMinor, $currency);
        $basic = Money::of($resolved->basicMinor, $currency);
        $allowances = Money::of($resolved->allowancesMinor, $currency);
        $overtime = Money::of($resolved->overtimeMinor, $currency);
        $bonus = Money::of($resolved->bonusMinor, $currency);

        if (bccomp(Bc::numeric($factor), '1', 10) < 0) {
            $gross = $gross->multiplyBy($factor);
            $taxable = $taxable->multiplyBy($factor);
            $pensionable = $pensionable->multiplyBy($factor);
            $zimdefBase = $zimdefBase->multiplyBy($factor);
            $necBase = $necBase->multiplyBy($factor);
            $basic = $basic->multiplyBy($factor);
            $allowances = $allowances->multiplyBy($factor);
            $overtime = $overtime->multiplyBy($factor);
            $bonus = $bonus->multiplyBy($factor);
            $trace->note("Pro-rated at {$factor} for unpaid leave / part month");
        }

        // 2 — NSSA PENSION (before PAYE; the employee contribution is deductible)
        $nssaConfig = $this->statutoryConfig->resolve('nssa_pension', $staff->school_id, $data->payDate, $currency);
        $ceiling = Money::of((int) $nssaConfig->configuration['ceiling_minor'], $currency);
        $insurable = $pensionable->compareTo($ceiling) > 0 ? $ceiling : $pensionable;
        $nssaEmployeeRate = (string) $nssaConfig->configuration['employee_rate'];
        $nssaEmployerRate = (string) $nssaConfig->configuration['employer_rate'];
        $nssaEmployee = $insurable->multiplyBy($nssaEmployeeRate);
        $nssaEmployer = $insurable->multiplyBy($nssaEmployerRate);
        $trace->nssa($pensionable, $ceiling, $insurable, $nssaEmployeeRate, $nssaEmployee);

        // 3 — PAYE, on the staff member's currency band table
        $payeConfig = $this->statutoryConfig->resolve('paye_bands', $staff->school_id, $data->payDate, $currency);
        $payeBase = $taxable->minus($nssaEmployee);
        $payeResult = $this->payeCalculator->apply($payeConfig->configuration, $payeBase, $currency);
        $paye = $payeResult['total'];
        $trace->paye($payeBase, $payeResult['bandsApplied'], $paye);

        // 4 — AIDS LEVY: a percentage OF THE PAYE, never of gross
        $aidsConfig = $this->statutoryConfig->resolve('aids_levy', $staff->school_id, $data->payDate);
        $aidsRate = (string) $aidsConfig->configuration['rate'];
        $aidsLevy = $paye->multiplyBy($aidsRate);
        $trace->aidsLevy($paye, $aidsRate, $aidsLevy);

        // 5 — EMPLOYER-BORNE LEVIES
        $apwcs = Money::zero($currency);
        $apwcsConfig = $this->statutoryConfig->resolveOptional('nssa_apwcs', $staff->school_id, $data->payDate);

        if ($apwcsConfig !== null) {
            $apwcsRate = (string) $apwcsConfig->configuration['employer_rate'];
            $apwcs = $pensionable->multiplyBy($apwcsRate);
            $trace->apwcs($pensionable, $apwcsRate, $apwcs);
        }

        $zimdefConfig = $this->statutoryConfig->resolve('zimdef', $staff->school_id, $data->payDate);
        $zimdefRate = (string) $zimdefConfig->configuration['rate'];
        $zimdef = $zimdefBase->multiplyBy($zimdefRate);
        $trace->zimdef($zimdefBase, $zimdef);

        // 6 — NEC, per the school's applicable CBA (optional — not every school has one)
        $necEmployee = Money::zero($currency);
        $necEmployer = Money::zero($currency);
        $necConfig = $this->statutoryConfig->resolveOptional('nec_dues', $staff->school_id, $data->payDate);

        if ($necConfig !== null) {
            $necEmployeeRate = (string) $necConfig->configuration['employee_rate'];
            $necEmployerRate = (string) $necConfig->configuration['employer_rate'];
            $necEmployee = $necBase->multiplyBy($necEmployeeRate);
            $necEmployer = $necBase->multiplyBy($necEmployerRate);
            $trace->nec($necBase, $necEmployeeRate, $necEmployerRate, $necEmployee, $necEmployer);
        }

        // 7 — OTHER DEDUCTIONS
        ['loan' => $loanDeduction, 'feeOffset' => $feeOffset] = $this->loanDeductions($staff->id, $currency);
        $thirdParty = Money::of($resolved->thirdPartyMinor, $currency);
        $otherDeductions = Money::of($resolved->otherDeductionsMinor, $currency);

        // 8 — NET
        $totalDeductions = $paye->plus($aidsLevy)->plus($nssaEmployee)->plus($necEmployee)
            ->plus($loanDeduction)->plus($feeOffset)->plus($thirdParty)->plus($otherDeductions);
        $net = $gross->minus($totalDeductions);

        if ($net->isNegative()) {
            throw PayrollComputationException::negativeNetPay($staff->id, $net->minor);
        }

        $employerCost = $gross->plus($nssaEmployer)->plus($apwcs)->plus($zimdef)->plus($necEmployer);

        return $this->transaction(function () use (
            $run, $staff, $data, $currency, $resolved, $basic, $allowances, $overtime, $bonus, $gross,
            $taxable, $pensionable, $paye, $aidsLevy, $nssaEmployee, $necEmployee, $loanDeduction,
            $feeOffset, $thirdParty, $otherDeductions, $totalDeductions, $net, $nssaEmployer, $apwcs,
            $zimdef, $necEmployer, $employerCost, $trace, $prorationResult,
        ): Payslip {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $staff->school_id,
                documentType: 'payslip',
                allocatedByUserId: $run->computed_by,
                academicYearId: $run->academic_year_id,
                termId: $run->term_id,
            ));

            $ytd = $this->priorYtd($staff->id, $data->payDate, $currency);

            $payslip = Payslip::create([
                'school_id' => $staff->school_id,
                'payroll_run_id' => $run->id,
                'staff_id' => $staff->id,
                'payslip_number' => $number->formatted_number,
                'basic_minor' => $basic->minor,
                'allowances_minor' => $allowances->minor,
                'overtime_minor' => $overtime->minor,
                'bonus_minor' => $bonus->minor,
                'gross_minor' => $gross->minor,
                'taxable_gross_minor' => $taxable->minor,
                'pensionable_gross_minor' => $pensionable->minor,
                'paye_minor' => $paye->minor,
                'aids_levy_minor' => $aidsLevy->minor,
                'nssa_employee_minor' => $nssaEmployee->minor,
                'nec_employee_minor' => $necEmployee->minor,
                'loan_deduction_minor' => $loanDeduction->minor,
                'third_party_minor' => $thirdParty->minor,
                'fee_offset_minor' => $feeOffset->minor,
                'other_deductions_minor' => $otherDeductions->minor,
                'total_deductions_minor' => $totalDeductions->minor,
                'net_pay_minor' => $net->minor,
                'nssa_employer_minor' => $nssaEmployer->minor,
                'apwcs_minor' => $apwcs->minor,
                'zimdef_minor' => $zimdef->minor,
                'nec_employer_minor' => $necEmployer->minor,
                'employer_cost_minor' => $employerCost->minor,
                'currency' => $currency->value,
                'ytd_gross_minor' => $ytd['gross']->plus($gross)->minor,
                'ytd_paye_minor' => $ytd['paye']->plus($paye)->minor,
                'ytd_nssa_minor' => $ytd['nssa']->plus($nssaEmployee)->minor,
                'unpaid_leave_days' => $prorationResult['unpaidDays'],
                'calculation_trace' => $trace->toArray(),
            ]);

            foreach ($resolved->lines as $sortOrder => $line) {
                PayslipLine::create([
                    'school_id' => $staff->school_id,
                    'payslip_id' => $payslip->id,
                    'component_id' => $line['component_id'],
                    'component_type' => $line['component_type'],
                    'description' => $line['description'],
                    'amount_minor' => $line['amount_minor'],
                    'currency' => $currency->value,
                    'is_taxable' => $line['is_taxable'],
                    'sort_order' => $sortOrder,
                ]);
            }

            return $payslip;
        });
    }

    private function assertEligible(Staff $staff, CarbonInterface $payDate): void
    {
        if ($staff->exited_on !== null && $staff->exited_on->lt($payDate)) {
            throw PayrollComputationException::staffExited($staff->id);
        }

        if ($staff->bank_account_number === null) {
            throw PayrollComputationException::missingBankDetails($staff->id);
        }

        if ($staff->zimra_bp_number === null && $staff->nssa_number === null) {
            throw PayrollComputationException::missingStatutoryIdentifiers($staff->id);
        }
    }

    private function assertContractNotExpired(int $staffId, StaffPayStructure $structure, CarbonInterface $payDate): void
    {
        if ($structure->contract_id === null) {
            return;
        }

        $contract = StaffContract::find($structure->contract_id);

        if ($contract !== null && $contract->ends_on !== null && $contract->ends_on->lt($payDate)) {
            throw PayrollComputationException::contractExpired($staffId, $contract->id);
        }
    }

    private function activeStructure(int $staffId, CarbonInterface $payDate): ?StaffPayStructure
    {
        return StaffPayStructure::where('staff_id', $staffId)
            ->where('status', 'active')
            ->where('effective_from', '<=', $payDate->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $payDate->toDateString()))
            ->first();
    }

    /**
     * @return array{loan: Money, feeOffset: Money}
     */
    private function loanDeductions(int $staffId, Currency $currency): array
    {
        $loans = StaffLoan::where('staff_id', $staffId)->where('status', 'active')->get();

        $loan = Money::zero($currency);
        $feeOffset = Money::zero($currency);

        foreach ($loans as $staffLoan) {
            $instalment = min($staffLoan->instalment_minor, $staffLoan->outstanding_minor);
            $amount = Money::of($instalment, $currency);

            if ($staffLoan->loan_type === 'fee_offset') {
                $feeOffset = $feeOffset->plus($amount);
            } else {
                $loan = $loan->plus($amount);
            }
        }

        return ['loan' => $loan, 'feeOffset' => $feeOffset];
    }

    /**
     * @return array{gross: Money, paye: Money, nssa: Money}
     */
    private function priorYtd(int $staffId, CarbonInterface $payDate, Currency $currency): array
    {
        $priorPayslips = Payslip::where('staff_id', $staffId)
            ->whereHas('payrollRun', fn ($q) => $q->whereYear('pay_date', $payDate->year)->where('pay_date', '<', $payDate->toDateString()))
            ->get();

        return [
            'gross' => Money::of((int) $priorPayslips->sum('gross_minor'), $currency),
            'paye' => Money::of((int) $priorPayslips->sum('paye_minor'), $currency),
            'nssa' => Money::of((int) $priorPayslips->sum('nssa_employee_minor'), $currency),
        ];
    }
}
