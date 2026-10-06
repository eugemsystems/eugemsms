<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\File;
use Modules\People\Domain\DataObjects\RecordGuardianVerificationData;
use Modules\People\Models\Guardian;
use Modules\People\Models\GuardianVerification;

/**
 * ACT-RecordGuardianVerification (Book C PPL-03 §3/BR-PPL-03-012). Records the
 * ID document and collection photo seen for a guardian. It is only a record
 * until someone else verifies it.
 */
final class RecordGuardianVerificationAction extends Action
{
    public const TYPES = ['national_id', 'passport', 'drivers_licence'];

    public function execute(RecordGuardianVerificationData $data): GuardianVerification
    {
        if (! in_array($data->documentType, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown document type [{$data->documentType}].");
        }

        if (! Guardian::query()->whereKey($data->guardianId)->exists()) {
            throw new InvalidArgumentException('That guardian does not belong to this school.');
        }

        foreach ([$data->documentFileId, $data->photoFileId] as $fileId) {
            if ($fileId !== null && ! File::query()->whereKey($fileId)->exists()) {
                throw new InvalidArgumentException('That file does not belong to this school.');
            }
        }

        return $this->transaction(fn (): GuardianVerification => GuardianVerification::create([
            'school_id' => $data->schoolId,
            'guardian_id' => $data->guardianId,
            'document_type' => $data->documentType,
            'document_file_id' => $data->documentFileId,
            'photo_file_id' => $data->photoFileId,
            'notes' => $data->notes,
            'created_at' => now(),
        ]));
    }
}
