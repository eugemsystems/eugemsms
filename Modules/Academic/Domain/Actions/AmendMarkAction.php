<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\AmendMarkData;
use Modules\Academic\Domain\Exceptions\MarkAmendmentRequiresApprovalException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMarkVersion;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-AmendMark (Book D ACA-05 §4 ⭐/BR-ACA-05-009/010). The direct
 * entry point for amending a mark on an assessment that is NOT yet
 * `published` — `submitted`/`moderated`/`approved` all amend
 * immediately through this action. A `published` assessment is
 * refused outright, always: amending one requires routing through
 * Core's real CORE-07 approvals engine via
 * `RequestMarkAmendmentAction` instead, and only
 * `MarkAmendmentRequest::onApproved()` (called by the approvals engine
 * itself once a request is actually approved) is a sanctioned caller
 * of `ApplyMarkAmendmentAction` for a published mark — there is no
 * caller-asserted "trust me, it's approved" flag here to bypass that,
 * unlike the earlier version of this action.
 */
final class AmendMarkAction extends Action
{
    public function __construct(
        private readonly ApplyMarkAmendmentAction $apply,
    ) {}

    public function execute(AmendMarkData $data): AssessmentMarkVersion
    {
        $assessment = Assessment::findOrFail($data->assessmentId);

        if ($assessment->status === 'published') {
            throw MarkAmendmentRequiresApprovalException::forAssessment($assessment->id, $data->studentId);
        }

        return $this->apply->execute($data);
    }
}
