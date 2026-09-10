<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\GuardianApproveSubjectSelectionData;
use Modules\Academic\Models\SubjectSelectionSubmission;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-GuardianApproveSubjectSelection (Book D ACA-02 §2/§6/BR-ACA-02-015).
 */
final class GuardianApproveSubjectSelectionAction extends Action
{
    public function execute(GuardianApproveSubjectSelectionData $data): SubjectSelectionSubmission
    {
        $submission = SubjectSelectionSubmission::findOrFail($data->submissionId);
        $submission->assertTransitionAllowed('guardian_approved');

        return $this->transaction(function () use ($submission, $data): SubjectSelectionSubmission {
            $submission->update([
                'status' => 'guardian_approved',
                'guardian_approved_by' => $data->approvedByUserId,
            ]);

            return $submission->fresh();
        });
    }
}
