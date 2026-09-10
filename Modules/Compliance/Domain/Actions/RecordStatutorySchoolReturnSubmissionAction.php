<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Domain\DataObjects\RecordStatutorySchoolReturnSubmissionData;
use Modules\Compliance\Models\StatutorySchoolReturn;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordStatutorySchoolReturnSubmission (Book H3 CMP-02 §3/
 * BR-CMP-02-007). The system prepares; a human submits through
 * MoPSE's own channel and records that it happened.
 */
final class RecordStatutorySchoolReturnSubmissionAction extends Action
{
    public function execute(RecordStatutorySchoolReturnSubmissionData $data): StatutorySchoolReturn
    {
        return $this->transaction(function () use ($data): StatutorySchoolReturn {
            $return = StatutorySchoolReturn::findOrFail($data->returnId);

            $return->update([
                'status' => 'submitted',
                'submitted_at' => Carbon::now(),
                'submitted_by' => $data->submittedByUserId,
                'acknowledgement_ref' => $data->acknowledgementRef,
            ]);

            return $return;
        });
    }
}
