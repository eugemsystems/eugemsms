<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\AmendVerifiedProjectData;
use Modules\Academic\Models\LearnerProject;
use Modules\Academic\Models\ProjectAmendmentRequest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RequestVerifiedProjectAmendment (Book E ACA-06 §6, mirroring
 * `RequestMarkAmendmentAction` from Book D ACA-05). The only
 * sanctioned way to amend a `verified` project's mark — raises a
 * `ProjectAmendmentRequest` through Core's real CORE-07 approvals
 * engine. Validates the reason length `ApplyVerifiedProjectAmendmentAction`
 * will check again at apply time (defence in depth, not trust).
 * Needs an active approval chain configured for the `project_amendment`
 * approvable type at this school (via the existing chain-builder
 * screen) — `RequestApprovalAction` throws `NO_APPROVAL_CHAIN`
 * otherwise, the same as any other first use of a new approvable type.
 */
final class RequestVerifiedProjectAmendmentAction extends Action
{
    public function __construct(
        private readonly RequestApprovalAction $requestApproval,
    ) {}

    public function execute(AmendVerifiedProjectData $data): ProjectAmendmentRequest
    {
        $learnerProject = LearnerProject::findOrFail($data->learnerProjectId);

        if ($learnerProject->status !== 'verified') {
            throw new InvalidStateTransitionException(
                "A project must be verified before it can be amended (currently [{$learnerProject->status}]).",
                ['learner_project_id' => $learnerProject->id, 'status' => $learnerProject->status],
            );
        }

        if (mb_strlen($data->changeReason) < 15) {
            throw new InvalidArgumentException('An amendment reason must be at least 15 characters.');
        }

        $newCriterionMarks = [];

        foreach ($data->criterionMarks as $input) {
            $newCriterionMarks[$input->criterion] = $input->mark;
        }

        return $this->transaction(function () use ($learnerProject, $data, $newCriterionMarks): ProjectAmendmentRequest {
            $request = ProjectAmendmentRequest::create([
                'school_id' => $learnerProject->school_id,
                'learner_project_id' => $learnerProject->id,
                'new_criterion_marks' => $newCriterionMarks,
                'change_reason' => $data->changeReason,
                'requested_by' => $data->changedByUserId,
                'status' => 'pending',
            ]);

            $approvalRequest = $this->requestApproval->execute(new RequestApprovalData(
                approvable: $request,
                schoolId: $learnerProject->school_id,
                academicYearId: $learnerProject->academic_year_id,
                requestedByUserId: $data->changedByUserId,
            ));

            $request->update(['approval_request_id' => $approvalRequest->id]);

            return $request->fresh();
        });
    }
}
