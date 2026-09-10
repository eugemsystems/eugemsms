<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Payroll\Domain\DataObjects\ComputePayrollRunData;
use Modules\Payroll\Domain\DataObjects\ComputePayslipData;
use Modules\Payroll\Domain\Events\NegativeNetPayDetected;
use Modules\Payroll\Domain\Events\PayrollComputed;
use Modules\Payroll\Domain\Events\PayrollException as PayrollExceptionEvent;
use Modules\Payroll\Domain\Events\UnconfirmedStatutoryConfigBlocking;
use Modules\Payroll\Domain\Exceptions\PayrollComputationException;
use Modules\Payroll\Domain\Exceptions\UnconfirmedStatutoryConfigException;
use Modules\Payroll\Domain\Support\Bc;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\People\Models\Staff;

/**
 * ACT-ComputePayrollRun (Book H3 PPL-05 §4/BR-PPL-05-002/012/013/014,
 * AC-PPL-05-001/006). The run's own "compute" step: every eligible
 * staff member is attempted, `PayrollComputationException`s are
 * collected rather than fatal (BR-PPL-05-012's "the run continues"),
 * and the run lands in `preview` — never `posted` — until
 * `ApprovePayrollRunAction` and `PostPayrollRunAction` each do their
 * own separate, explicit thing (BR-PPL-05-013's four distinct
 * states).
 */
final class ComputePayrollRunAction extends Action
{
    private const array ELIGIBLE_STAFF_STATUSES = ['probation', 'active', 'on_leave', 'suspended', 'notice'];

    public function __construct(
        private readonly AllocateNumberAction $allocateNumber,
        private readonly ComputePayslipAction $computePayslip,
        private readonly SettingResolver $settings,
    ) {}

    public function execute(ComputePayrollRunData $data): PayrollRun
    {
        $run = $this->transaction(function () use ($data): PayrollRun {
            $number = $this->allocateNumber->execute(new AllocateNumberData(
                schoolId: $data->schoolId,
                documentType: 'payroll_run',
                allocatedByUserId: $data->computedByUserId,
                academicYearId: $data->academicYearId,
                termId: $data->termId,
            ));

            return PayrollRun::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'period_month' => $data->payDate->format('Y-m'),
                'run_number' => $number->formatted_number,
                'run_type' => $data->runType,
                'pay_date' => $data->payDate->toDateString(),
                'period_start' => $data->periodStart->toDateString(),
                'period_end' => $data->periodEnd->toDateString(),
                'status' => 'computing',
                'computed_by' => $data->computedByUserId,
            ]);
        });

        $staffIds = Staff::where('school_id', $data->schoolId)
            ->whereIn('status', self::ELIGIBLE_STAFF_STATUSES)
            ->pluck('id');

        $exceptions = [];
        $payslips = collect();

        foreach ($staffIds as $staffId) {
            try {
                $payslips->push($this->computePayslip->execute(new ComputePayslipData(
                    payrollRunId: $run->id,
                    staffId: $staffId,
                    payDate: $data->payDate,
                    periodStart: $data->periodStart,
                    periodEnd: $data->periodEnd,
                )));
            } catch (UnconfirmedStatutoryConfigException $e) {
                event(new UnconfirmedStatutoryConfigBlocking($data->schoolId, $run->id, $e->getMessage()));

                throw $e;
            } catch (PayrollComputationException $e) {
                $exceptions[] = ['staff_id' => $e->staffId, 'reason' => $e->reason, 'message' => $e->getMessage()];

                if ($e->reason === 'negative_net_pay') {
                    event(new NegativeNetPayDetected($run->id, $e->staffId, (int) $e->details()['net_minor']));
                } else {
                    event(new PayrollExceptionEvent($run->id, $e->staffId, $e->reason));
                }
            }
        }

        $variancePercent = (int) $this->settings->get('payroll.variance_alert_percent', new ScopeChain(schoolId: $data->schoolId));
        $varianceReport = $this->buildVarianceReport($payslips, $data->payDate, $variancePercent);

        $run->update([
            'status' => 'preview',
            'staff_count' => $payslips->count(),
            'gross_minor' => (int) $payslips->sum('gross_minor'),
            'deductions_minor' => (int) $payslips->sum('total_deductions_minor'),
            'net_minor' => (int) $payslips->sum('net_pay_minor'),
            'employer_cost_minor' => (int) $payslips->sum('employer_cost_minor'),
            'paye_minor' => (int) $payslips->sum('paye_minor'),
            'aids_levy_minor' => (int) $payslips->sum('aids_levy_minor'),
            'nssa_employee_minor' => (int) $payslips->sum('nssa_employee_minor'),
            'nssa_employer_minor' => (int) $payslips->sum('nssa_employer_minor'),
            'apwcs_minor' => (int) $payslips->sum('apwcs_minor'),
            'zimdef_minor' => (int) $payslips->sum('zimdef_minor'),
            'nec_employee_minor' => (int) $payslips->sum('nec_employee_minor'),
            'nec_employer_minor' => (int) $payslips->sum('nec_employer_minor'),
            'exception_report' => $exceptions,
            'variance_report' => $varianceReport,
        ]);

        $run = $run->fresh();
        event(new PayrollComputed($run));

        return $run;
    }

    /**
     * @param  Collection<int, Payslip>  $payslips
     * @return array<int, array<string, mixed>>
     */
    private function buildVarianceReport(Collection $payslips, CarbonInterface $payDate, int $thresholdPercent): array
    {
        $priorMonth = $payDate->copy()->subMonthNoOverflow()->format('Y-m');
        $variances = [];

        foreach ($payslips as $payslip) {
            $priorPayslip = Payslip::where('staff_id', $payslip->staff_id)
                ->whereHas('payrollRun', fn ($q) => $q->where('period_month', $priorMonth))
                ->first();

            if ($priorPayslip === null || $priorPayslip->net_pay_minor === 0) {
                continue;
            }

            $changePercent = bcmul(
                bcdiv((string) ($payslip->net_pay_minor - $priorPayslip->net_pay_minor), (string) $priorPayslip->net_pay_minor, 4),
                '100',
                2,
            );

            if (bccomp(Bc::numeric($this->abs($changePercent)), (string) $thresholdPercent, 2) > 0) {
                $variances[] = [
                    'staff_id' => $payslip->staff_id,
                    'prior_net_minor' => $priorPayslip->net_pay_minor,
                    'current_net_minor' => $payslip->net_pay_minor,
                    'change_percent' => $changePercent,
                ];
            }
        }

        return $variances;
    }

    private function abs(string $decimal): string
    {
        return str_starts_with($decimal, '-') ? substr($decimal, 1) : $decimal;
    }
}
