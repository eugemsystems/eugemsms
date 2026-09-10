<?php

declare(strict_types=1);

namespace Modules\Security\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Security\Domain\DataObjects\CreateContractorData;
use Modules\Security\Models\Contractor;

/**
 * ACT-CreateContractor (Book H2 OPS-06 §2/BR-OPS-06-001). Always
 * created `pending` — site access is granted only once
 * `ApproveContractorAction` runs and insurance/induction are both on
 * file and current.
 */
final class CreateContractorAction extends Action
{
    public function execute(CreateContractorData $data): Contractor
    {
        return $this->transaction(fn (): Contractor => Contractor::create([
            'school_id' => $data->schoolId,
            'supplier_id' => $data->supplierId,
            'company_name' => $data->companyName,
            'contact_person' => $data->contactPerson,
            'phone' => $data->phone,
            'work_type' => $data->workType,
            'status' => 'pending',
        ]));
    }
}
