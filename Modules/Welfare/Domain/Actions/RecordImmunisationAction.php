<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordImmunisationData;
use Modules\Welfare\Models\Immunisation;

/**
 * ACT-RecordImmunisation (Book G BRD-06 §2).
 */
final class RecordImmunisationAction extends Action
{
    public function execute(RecordImmunisationData $data): Immunisation
    {
        return $this->transaction(fn (): Immunisation => Immunisation::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'vaccine' => $data->vaccine,
            'dose_number' => $data->doseNumber,
            'administered_on' => $data->administeredOn?->toDateString(),
            'administered_by' => $data->administeredBy,
            'batch_number' => $data->batchNumber,
            'next_due_on' => $data->nextDueOn?->toDateString(),
            'certificate_file_id' => $data->certificateFileId,
            'status' => $data->status,
            'decline_reason' => $data->declineReason,
        ]));
    }
}
