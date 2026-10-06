<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\AcademicYear;
use Modules\Finance\Domain\DataObjects\CreateBudgetEnvelopeData;
use Modules\Finance\Models\DiscountScheme;
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
        DiscountScheme::query()->where('school_id', $data->schoolId)->findOrFail($data->schemeId);
        AcademicYear::query()->where('school_id', $data->schoolId)->findOrFail($data->academicYearId);

        if ($data->budgetMinor !== null && ($data->budgetMinor < 0 || $data->currency === null)) {
            throw new InvalidArgumentException('A capped envelope needs a non-negative budget and a currency; leave the budget empty for uncapped.');
        }

        if (SchemeBudgetEnvelope::query()->where('school_id', $data->schoolId)->where('scheme_id', $data->schemeId)->where('academic_year_id', $data->academicYearId)->exists()) {
            throw new InvalidArgumentException('This scheme already has an envelope for that year.');
        }

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
