<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\Actions;

use Illuminate\Support\Collection;
use Modules\Core\Domain\Actions\Action;
use Modules\Welfare\Models\RiskAssessment;

/**
 * ACT-ListOverdueRiskAssessments (Book G BRD-08 §2/BR-BRD-08-019 —
 * "overdue assessments appear on the lead's dashboard").
 */
final class ListOverdueRiskAssessmentsAction extends Action
{
    /**
     * @return Collection<int, RiskAssessment>
     */
    public function execute(int $schoolId): Collection
    {
        return RiskAssessment::query()
            ->where('school_id', $schoolId)
            ->whereDate('review_due_on', '<', now()->toDateString())
            ->get();
    }
}
