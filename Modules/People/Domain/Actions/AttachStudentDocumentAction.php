<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;
use Modules\People\Domain\DataObjects\AttachStudentDocumentData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentDocument;

/**
 * ACT-AttachDocument (Book C PPL-01 §5). Puts a document from the file vault on
 * a learner's record. Cross-border study permits and the like carry an expiry
 * so BR-PPL-01-021's alerts can fire; a document starts unverified.
 */
final class AttachStudentDocumentAction extends Action
{
    public const TYPES = [
        'birth_certificate', 'national_registration', 'passport', 'study_permit', 'transfer_letter',
        'previous_report', 'immunisation', 'guardianship_order', 'court_order',
    ];

    public function execute(AttachStudentDocumentData $data): StudentDocument
    {
        if (! in_array($data->documentType, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown document type [{$data->documentType}].");
        }

        if (! Student::query()->whereKey($data->studentId)->exists() || ! File::query()->whereKey($data->fileId)->exists()) {
            throw new InvalidArgumentException('That learner or file does not belong to this school.');
        }

        if ($data->issuedOn !== null && $data->expiresOn !== null && $data->expiresOn->lt($data->issuedOn)) {
            throw new InvalidArgumentException('A document cannot expire before it was issued.');
        }

        return $this->transaction(fn (): StudentDocument => StudentDocument::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'document_type' => $data->documentType,
            'file_id' => $data->fileId,
            'reference_number' => $data->referenceNumber,
            'issued_on' => $data->issuedOn?->toDateString(),
            'expires_on' => $data->expiresOn?->toDateString(),
            'notes' => $data->notes,
            'is_verified' => false,
            'is_original_sighted' => false,
            'uploaded_by' => $data->uploadedByUserId,
            'created_at' => Carbon::now(),
        ]));
    }
}
