<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CreateBudgetEnvelopeData;
use Modules\Finance\Models\SchemeBudgetEnvelope;

/**
 * ACT-CreateBudgetEnvelope (Book K FIN-07 §2/§4/BR-FIN-07-009 ⭐).
 * `budgetMinor` null = uncapped (sibling, staff-child — schemes the
 * school commits to unconditionally, not a pool to exhaust).
 */
final class CreateBudgetEnvelopeAction extends Action
{
    public function execute(CreateBudgetEnvelopeData $data): SchemeBudgetEnvelope
    {
        return $this->transaction(fn (): SchemeBudgetEnvelope => SchemeBudgetEnvelope::create([
            'school_id' => $data->schoolId,
            'scheme_id' => $data->schemeId,
            'academic_year_id' => $data->academicYearId,
            'budget_minor' => $data->budgetMinor,
            'currency' => $data->currency,
            'committed_minor' => 0,
            'utilised_minor' => 0,
        ]));
    }
}
