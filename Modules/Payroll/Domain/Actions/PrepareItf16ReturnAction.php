<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Payroll\Domain\DataObjects\PrepareItf16ReturnData;
use Modules\Payroll\Domain\Exceptions\Itf16ReconciliationException;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * ACT-PrepareItf16Return (Book H3 PPL-05 §5/BR-PPL-05-023
 * (AC-PPL-05-011)), deliberately separate from
 * `PrepareStatutoryReturnsAction` — that action's own docblock defers
 * ITF16 explicitly. This is the annual PAYE reconciliation: every
 * payslip's PAYE for the tax year, summed per employee, must
 * reconcile to the sum of the twelve monthly `p2_paye`
 * `StatutoryReturn` rows for the same year. A mismatch — or a year
 * with fewer than twelve monthly P2 returns on file — blocks
 * preparation outright rather than producing a return that doesn't
 * actually reconcile.
 */
final class PrepareItf16ReturnAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(PrepareItf16ReturnData $data): StatutoryReturn
    {
        $payslips = Payslip::where('school_id', $data->schoolId)
            ->whereHas('payrollRun', fn ($q) => $q->whereYear('pay_date', $data->taxYear))
            ->get();

        $payslipTotalMinor = (int) $payslips->sum('paye_minor');

        $p2Returns = StatutoryReturn::where('school_id', $data->schoolId)
            ->where('return_type', 'p2_paye')
            ->where('period_reference', 'like', "{$data->taxYear}-%")
            ->get();

        if ($p2Returns->count() < 12) {
            throw Itf16ReconciliationException::incompleteP2Returns($data->schoolId, $data->taxYear, $p2Returns->count());
        }

        $p2TotalMinor = (int) $p2Returns->sum('amount_due_minor');

        if ($payslipTotalMinor !== $p2TotalMinor) {
            throw Itf16ReconciliationException::mismatch($data->schoolId, $data->taxYear, $payslipTotalMinor, $p2TotalMinor);
        }

        $perEmployee = $payslips
            ->groupBy('staff_id')
            ->map(fn ($group) => [
                'staff_id' => $group->first()->staff_id,
                'gross_minor' => (int) $group->sum('gross_minor'),
                'paye_minor' => (int) $group->sum('paye_minor'),
                'nssa_minor' => (int) $group->sum('nssa_employee_minor'),
            ])
            ->values()
            ->all();

        $dueDate = $this->dueDate($data->schoolId, $data->taxYear);
        $firstPayslip = $payslips->first();
        $currency = $firstPayslip !== null ? $firstPayslip->currency : 'USD';

        return $this->transaction(fn (): StatutoryReturn => StatutoryReturn::updateOrCreate(
            ['school_id' => $data->schoolId, 'return_type' => 'itf16_annual', 'period_reference' => (string) $data->taxYear],
            [
                'period_type' => 'annual',
                'due_date' => $dueDate->toDateString(),
                'amount_due_minor' => $payslipTotalMinor,
                'currency' => $currency,
                'supporting_data' => ['per_employee' => $perEmployee, 'reconciled_p2_returns' => $p2Returns->pluck('id')->all()],
                'status' => 'pending',
            ],
        ));
    }

    private function dueDate(int $schoolId, int $taxYear): Carbon
    {
        $monthDay = (string) $this->settings->get('payroll.itf16_due_date', new ScopeChain(schoolId: $schoolId));
        [$month, $day] = array_map('intval', explode('-', $monthDay));

        return Carbon::create($taxYear + 1, $month, $day);
    }
}
