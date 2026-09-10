<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Payroll\Domain\DataObjects\PrepareStatutoryReturnsData;
use Modules\Payroll\Domain\Events\StatutoryReturnPrepared;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * ACT-PrepareStatutoryReturns (Book H3 PPL-05 §4/BR-PPL-05-020
 * (AC-PPL-05-007)). Called automatically from `PostPayrollRunAction`.
 * A school running more than one regular payroll run within the same
 * `period_month` (a supplementary run, say) accumulates onto the same
 * return row rather than creating a second one — the unique
 * constraint is `(school_id, return_type, period_reference)`.
 *
 * ITF16 (the annual PAYE reconciliation, BR-PPL-05-023) is
 * deliberately NOT prepared here — only the five monthly returns this
 * rule names are. `PrepareItf16ReturnAction` is the separate, annual
 * entry point; it reconciles against the twelve monthly `p2_paye`
 * rows this action accumulates.
 */
final class PrepareStatutoryReturnsAction extends Action
{
    /**
     * @var array<string, string>
     */
    private const array MONTHLY_RETURN_AMOUNT_FIELDS = [
        'p2_paye' => 'paye_minor',
        'nssa_monthly' => 'nssa_combined',
        'nec_monthly' => 'nec_combined',
        'zimdef' => 'zimdef_minor',
        'aids_levy' => 'aids_levy_minor',
    ];

    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    /**
     * @return array<int, StatutoryReturn>
     */
    public function execute(PrepareStatutoryReturnsData $data): array
    {
        $run = PayrollRun::with('payslips')->findOrFail($data->payrollRunId);

        $firstPayslip = $run->payslips->first();

        if ($firstPayslip === null) {
            throw new InvalidStateTransitionException('Statutory returns cannot be prepared for a run with no payslips.', ['payroll_run_id' => $run->id]);
        }

        $currency = $firstPayslip->currency;
        $dueDay = (int) $this->settings->get('payroll.statutory_due_day', new ScopeChain(schoolId: $run->school_id));
        $dueDate = Carbon::parse($run->pay_date)->addMonthNoOverflow()->startOfMonth()->addDays($dueDay - 1);
        $periodReference = $run->period_month;

        $returns = [];

        foreach (self::MONTHLY_RETURN_AMOUNT_FIELDS as $returnType => $amountField) {
            $amountMinor = match ($amountField) {
                'nssa_combined' => $run->nssa_employee_minor + $run->nssa_employer_minor,
                'nec_combined' => $run->nec_employee_minor + $run->nec_employer_minor,
                default => $run->{$amountField},
            };

            $return = $this->transaction(function () use ($run, $returnType, $periodReference, $dueDate, $amountMinor, $currency): StatutoryReturn {
                $existing = StatutoryReturn::where('school_id', $run->school_id)
                    ->where('return_type', $returnType)
                    ->where('period_reference', $periodReference)
                    ->first();

                if ($existing !== null) {
                    $existing->update([
                        'amount_due_minor' => $existing->amount_due_minor + $amountMinor,
                        'supporting_data' => [...$existing->supporting_data, ['payroll_run_id' => $run->id, 'amount_minor' => $amountMinor]],
                    ]);

                    return $existing;
                }

                return StatutoryReturn::create([
                    'school_id' => $run->school_id,
                    'return_type' => $returnType,
                    'period_type' => 'monthly',
                    'period_reference' => $periodReference,
                    'due_date' => $dueDate->toDateString(),
                    'amount_due_minor' => $amountMinor,
                    'currency' => $currency,
                    'supporting_data' => [['payroll_run_id' => $run->id, 'amount_minor' => $amountMinor]],
                    'status' => 'pending',
                ]);
            });

            event(new StatutoryReturnPrepared($return));
            $returns[] = $return;
        }

        return $returns;
    }
}
