<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RejectSubjectSelectionData;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RejectSubjectSelection (Book D ACA-02 §2/§6).
 */
final class RejectSubjectSelectionAction extends Action
{
    public function execute(RejectSubjectSelectionData $data): SubjectSelectionSubmission
    {
        $submission = SubjectSelectionSubmission::findOrFail($data->submissionId);
        $submission->assertTransitionAllowed('rejected');

        return $this->transaction(function () use ($submission, $data): SubjectSelectionSubmission {
            $submission->update([
                'status' => 'rejected',
                'rejection_reason' => $data->rejectionReason,
            ]);

            return $submission->fresh();
        });
    }
}
