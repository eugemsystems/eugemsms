<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateStaffContractData;
use Modules\People\Domain\Events\ContractStarted;
use Modules\People\Domain\Exceptions\ActiveContractExistsException;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffContract;

/**
 * ACT-CreateStaffContract (Book C PPL-04 §4/BR-PPL-04-002). No
 * separate approval workflow exists in this pass — a contract is
 * created directly `active`, matching this module's other
 * screens-deferred cuts.
 */
final class CreateStaffContractAction extends Action
{
    public function execute(CreateStaffContractData $data): StaffContract
    {
        $staff = Staff::findOrFail($data->staffId);

        if ($staff->activeContract() !== null) {
            throw ActiveContractExistsException::forStaff($staff->id);
        }

        return $this->transaction(function () use ($data): StaffContract {
            $contract = StaffContract::create([
                'school_id' => $data->schoolId,
                'staff_id' => $data->staffId,
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
                'signed_on' => $data->signedOn?->toDateString(),
                'status' => 'active',
                'created_by' => $data->createdByUserId,
            ]);

            event(new ContractStarted($contract));

            return $contract;
        });
    }
}
