<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\CreateStatutoryDocumentData;
use Modules\Compliance\Models\StatutoryDocument;
use Modules\Core\Domain\Actions\Action;

final class CreateStatutoryDocumentAction extends Action
{
    public function execute(CreateStatutoryDocumentData $data): StatutoryDocument
    {
        return $this->transaction(fn (): StatutoryDocument => StatutoryDocument::create([
            'school_id' => $data->schoolId,
            'document_type' => $data->documentType,
            'reference_number' => $data->referenceNumber,
            'issuing_authority' => $data->issuingAuthority,
            'issued_on' => $data->issuedOn,
            'expires_on' => $data->expiresOn,
            'file_id' => $data->fileId,
            'renewal_lead_days' => $data->renewalLeadDays,
            'responsible_staff_id' => $data->responsibleStaffId,
            'status' => 'valid',
        ]));
    }
}
