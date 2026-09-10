<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Domain\DataObjects\RecordRiskAssessmentData;
use Modules\Welfare\Models\RiskAssessment;

/**
 * ACT-RecordRiskAssessment (Book G BRD-08 §2/BR-BRD-08-019). Carries
 * a mandatory review date — `Modules\Welfare\Domain\Actions\ListOverdueRiskAssessmentsAction`
 * (see below in this module) is what surfaces an overdue one on the
 * lead's dashboard.
 */
final class RecordRiskAssessmentAction extends Action
{
    public function execute(RecordRiskAssessmentData $data): RiskAssessment
    {
        return $this->transaction(fn (): RiskAssessment => RiskAssessment::create([
            'school_id' => $data->schoolId,
            'case_id' => $data->caseId,
            'assessed_at' => $data->assessedAt,
            'assessed_by' => $data->assessedByUserId,
            'risk_factors' => json_encode($data->riskFactors, JSON_THROW_ON_ERROR),
            'protective_factors' => $data->protectiveFactors === null ? null : json_encode($data->protectiveFactors, JSON_THROW_ON_ERROR),
            'risk_level' => $data->riskLevel,
            'rationale' => $data->rationale,
            'mitigation_plan' => $data->mitigationPlan,
            'review_due_on' => $data->reviewDueOn->toDateString(),
        ]));
    }
}
