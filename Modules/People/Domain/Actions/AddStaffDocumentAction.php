<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\AddStaffDocumentData;
use Modules\People\Models\StaffDocument;

/**
 * ACT-AddStaffDocument (Book C PPL-04 §2/BR-PPL-04-018).
 */
final class AddStaffDocumentAction extends Action
{
    public function execute(AddStaffDocumentData $data): StaffDocument
    {
        return $this->transaction(fn (): StaffDocument => StaffDocument::create([
            'school_id' => $data->schoolId,
            'staff_id' => $data->staffId,
            'document_type' => $data->documentType,
            'file_id' => $data->fileId,
            'reference_number' => $data->referenceNumber,
            'issued_on' => $data->issuedOn?->toDateString(),
            'expires_on' => $data->expiresOn?->toDateString(),
            'is_verified' => false,
        ]));
    }
}
