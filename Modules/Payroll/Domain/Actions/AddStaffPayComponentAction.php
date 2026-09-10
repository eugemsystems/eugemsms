<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\AddStaffPayComponentData;
use Modules\Payroll\Models\StaffPayComponent;

final class AddStaffPayComponentAction extends Action
{
    public function execute(AddStaffPayComponentData $data): StaffPayComponent
    {
        return $this->transaction(fn (): StaffPayComponent => StaffPayComponent::create([
            'school_id' => $data->schoolId,
            'pay_structure_id' => $data->payStructureId,
            'component_id' => $data->componentId,
            'amount_minor' => $data->amountMinor,
            'percent' => $data->percent,
            'currency' => $data->currency,
            'quantity' => $data->quantity,
            'effective_from' => $data->effectiveFrom->toDateString(),
            'notes' => $data->notes,
        ]));
    }
}
