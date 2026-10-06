<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\SchemeBudgetEnvelope;

/**
 * ACT-PreviewAwardEnvelope (Book K FIN-07 §5, "envelope check shown live
 * before submission"). Read-only: for a scheme and year, what the capped
 * envelope still has and whether a fixed amount would fit. `GrantAwardAction`
 * enforces exactly this check; the preview only lets the screen show it
 * first. A percentage award has no knowable amount until it is billed, so
 * only the billing-time check (`AwardDiscountResolver`) can bound it.
 */
final class PreviewAwardEnvelopeAction extends Action
{
    protected bool $transactional = false;

    /**
     * @return array{capped: bool, currency: string|null, budget_minor: int|null, remaining_minor: int|null, shortfall_minor: int}
     */
    public function execute(int $schoolId, int $schemeId, int $academicYearId, ?int $awardAmountMinor = null): array
    {
        $envelope = SchemeBudgetEnvelope::query()
            ->where('school_id', $schoolId)->where('scheme_id', $schemeId)->where('academic_year_id', $academicYearId)->first();

        if ($envelope === null || $envelope->budget_minor === null) {
            return ['capped' => false, 'currency' => $envelope === null ? null : $envelope->currency, 'budget_minor' => null, 'remaining_minor' => null, 'shortfall_minor' => 0];
        }

        $remaining = $envelope->remainingMinor() ?? 0;

        return [
            'capped' => true,
            'currency' => $envelope->currency,
            'budget_minor' => $envelope->budget_minor,
            'remaining_minor' => $remaining,
            'shortfall_minor' => $awardAmountMinor === null ? 0 : max(0, $awardAmountMinor - max(0, $remaining)),
        ];
    }
}
