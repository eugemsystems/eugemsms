<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\RecordStatutoryReturnSubmissionData;
use Modules\Payroll\Models\StatutoryReturn;

/**
 * ACT-RecordStatutoryReturnSubmission (Book H3 PPL-05 §5/BR-PPL-05-022).
 * The system prepares and exports returns; it does not file them —
 * this only records that a human already did, with the reference
 * they were given.
 */
final class RecordStatutoryReturnSubmissionAction extends Action
{
    public function execute(RecordStatutoryReturnSubmissionData $data): StatutoryReturn
    {
        $return = StatutoryReturn::findOrFail($data->statutoryReturnId);

        return $this->transaction(fn (): StatutoryReturn => tap($return)->update([
            'status' => 'submitted',
            'submitted_at' => Carbon::now(),
            'submission_reference' => $data->submissionReference,
        ]));
    }
}
