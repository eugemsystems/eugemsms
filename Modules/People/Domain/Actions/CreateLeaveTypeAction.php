<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateLeaveTypeData;
use Modules\People\Models\LeaveType;

/**
 * ACT-CreateLeaveType (Book C PPL-04 §2).
 */
final class CreateLeaveTypeAction extends Action
{
    public function execute(CreateLeaveTypeData $data): LeaveType
    {
        return $this->transaction(fn (): LeaveType => LeaveType::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'annual_entitlement_days' => $data->annualEntitlementDays,
            'accrual_method' => $data->accrualMethod,
            'is_paid' => $data->isPaid,
            'requires_document' => $data->requiresDocument,
            'max_consecutive_days' => $data->maxConsecutiveDays,
            'carry_forward_days' => $data->carryForwardDays,
            'requires_cover' => $data->requiresCover,
            'is_active' => true,
        ]));
    }
}
