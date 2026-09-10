<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Domain\DataObjects\RenewStaffContractData;
use Modules\People\Domain\Events\ContractStarted;
use Modules\People\Models\StaffContract;

/**
 * ACT-RenewStaffContract (Book C PPL-04 §4/BR-PPL-04-002). "Renewal
 * creates a new contract linked to the prior one" — the old contract
 * is never overwritten in place.
 */
final class RenewStaffContractAction extends Action
{
    public function execute(RenewStaffContractData $data): StaffContract
    {
        $current = StaffContract::findOrFail($data->contractId);

        if ($current->status !== 'active') {
            throw new InvalidStateTransitionException(
                "Only an active contract can be renewed; this one is [{$current->status}].",
                ['status' => $current->status],
            );
        }

        return $this->transaction(function () use ($current, $data): StaffContract {
            $renewed = StaffContract::create([
                'school_id' => $current->school_id,
                'staff_id' => $current->staff_id,
                'contract_type' => $data->contractType,
                'starts_on' => $data->startsOn->toDateString(),
                'ends_on' => $data->endsOn?->toDateString(),
                'probation_months' => $data->probationMonths,
                'notice_period_days' => $data->noticePeriodDays,
                'weekly_hours' => $data->weeklyHours,
                'basic_salary_minor' => $data->basicSalaryMinor,
                'salary_currency' => $data->salaryCurrency,
                'salary_grade' => $data->salaryGrade,
                'salary_notch' => $data->salaryNotch,
                'status' => 'active',
                'created_by' => $data->renewedByUserId,
            ]);

            $current->update([
                'status' => 'renewed',
                'renewed_to_contract_id' => $renewed->id,
            ]);

            event(new ContractStarted($renewed));

            return $renewed;
        });
    }
}
