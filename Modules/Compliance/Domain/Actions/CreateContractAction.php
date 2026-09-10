<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateContractData;
use Modules\Compliance\Models\Contract;
use Modules\Core\Domain\Actions\Action;

final class CreateContractAction extends Action
{
    public function execute(CreateContractData $data): Contract
    {
        return $this->transaction(fn (): Contract => Contract::create([
            'school_id' => $data->schoolId,
            'counterparty_name' => $data->counterpartyName,
            'contract_type' => $data->contractType,
            'description' => $data->description,
            'starts_on' => $data->startsOn,
            'expires_on' => $data->expiresOn,
            'renewal_lead_days' => $data->renewalLeadDays,
            'document_file_id' => $data->documentFileId,
            'responsible_staff_id' => $data->responsibleStaffId,
            'status' => 'active',
        ]));
    }
}
