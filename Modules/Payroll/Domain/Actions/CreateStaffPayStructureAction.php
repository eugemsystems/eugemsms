<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\CreateStaffPayStructureData;
use Modules\Payroll\Models\StaffPayStructure;

/**
 * ACT-CreateStaffPayStructure (Book H3 PPL-05 §2 ⭐). Any existing
 * `active` structure for this staff member is superseded — never
 * overwritten — mirroring `CreateStatutoryConfigurationAction`'s own
 * supersession pattern. A payslip already computed against the prior
 * structure is unaffected (it stored its own amounts at compute
 * time), matching BR-PPL-05-004's spirit for pay data generally.
 */
final class CreateStaffPayStructureAction extends Action
{
    public function execute(CreateStaffPayStructureData $data): StaffPayStructure
    {
        return $this->transaction(function () use ($data): StaffPayStructure {
            StaffPayStructure::where('staff_id', $data->staffId)
                ->where('status', 'active')
                ->update(['status' => 'superseded', 'effective_to' => $data->effectiveFrom->copy()->subDay()->toDateString()]);

            return StaffPayStructure::create([
                'school_id' => $data->schoolId,
                'staff_id' => $data->staffId,
                'contract_id' => $data->contractId,
                'grade_id' => $data->gradeId,
                'notch' => $data->notch,
                'primary_currency' => $data->primaryCurrency,
                'payment_currency' => $data->paymentCurrency,
                'effective_from' => $data->effectiveFrom->toDateString(),
                'status' => 'active',
                'approved_by' => $data->approvedByUserId,
            ]);
        });
    }
}
