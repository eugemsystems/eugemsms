<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\MarkAmendmentRequest;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Approvals\RequestApprovalAction;
use Modules\Core\Domain\DataObjects\Approvals\RequestApprovalData;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-RequestMarkAmendment (Book D ACA-05 §4 ⭐/BR-ACA-05-010). The
 * only sanctioned way to amend a mark on a `published` assessment —
 * raises a `MarkAmendmentRequest` through Core's real CORE-07
 * approvals engine rather than applying anything itself. Validates
 * the same reason-length/mark-range shape `ApplyMarkAmendmentAction`
 * will check again at apply time (defence in depth, not trust): a
 * malformed request should fail here, immediately, rather than sit in
 * an approver's queue for days only to error when finally approved.
 * Needs an active approval chain configured for the `mark_amendment`
 * approvable type at this school (via the existing chain-builder
 * screen) — `RequestApprovalAction` throws `NO_APPROVAL_CHAIN`
 * otherwise, the same as any other first use of a new approvable type.
 */
final class RequestMarkAmendmentAction extends Action
{
    public function __construct(
        private readonly RequestApprovalAction $requestApproval,
    ) {}

    public function execute(AmendMarkData $data): MarkAmendmentRequest
    {
        $assessment = Assessment::findOrFail($data->assessmentId);

        if ($assessment->status !== 'published') {
            throw new InvalidStateTransitionException(
                "Only a published assessment routes through approval — this one is [{$assessment->status}]; amend it directly via AmendMarkAction.",
                ['assessment_id' => $assessment->id, 'status' => $assessment->status],
            );
        }

        AssessmentMark::query()
            ->where('assessment_id', $assessment->id)
            ->where('student_id', $data->studentId)
            ->firstOrFail();

        if (mb_strlen($data->changeReason) < 15) {
            throw new InvalidArgumentException('An amendment reason must be at least 15 characters.');
        }

        if (! $data->isAbsent && $data->rawMark === null) {
            throw new InvalidArgumentException('A raw mark is required unless the learner is marked absent.');
        }

        $maxMark = (float) $assessment->max_mark;

        if (! $data->isAbsent && ($data->rawMark < 0 || $data->rawMark > $maxMark)) {
            throw MarkOutOfRangeException::forMark($data->rawMark, $maxMark);
        }

        return $this->transaction(function () use ($assessment, $data): MarkAmendmentRequest {
            $request = MarkAmendmentRequest::create([
                'school_id' => $assessment->school_id,
                'assessment_id' => $assessment->id,
                'student_id' => $data->studentId,
                'new_raw_mark' => $data->isAbsent ? null : $data->rawMark,
                'new_is_absent' => $data->isAbsent,
                'change_reason' => $data->changeReason,
                'requested_by' => $data->changedByUserId,
                'status' => 'pending',
            ]);

            $approvalRequest = $this->requestApproval->execute(new RequestApprovalData(
                approvable: $request,
                schoolId: $assessment->school_id,
                academicYearId: $assessment->academic_year_id,
                requestedByUserId: $data->changedByUserId,
                termId: $assessment->term_id,
            ));

            $request->update(['approval_request_id' => $approvalRequest->id]);

            return $request->fresh();
        });
    }
}
