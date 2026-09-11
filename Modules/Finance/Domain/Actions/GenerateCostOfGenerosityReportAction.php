<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Domain\DataObjects\CostOfGenerositySchemeSummary;
use Modules\Finance\Models\AwardUtilisation;

/**
 * ACT-GenerateCostOfGenerosityReport (Book K FIN-07 §4/BR-FIN-07-014
 * (AC-FIN-07-006)). Reads `award_utilisation` — the append-only,
 * already-posted truth (BR-FIN-07-013) — never a live recomputation.
 * "Gross" per scheme is the sum of DISTINCT fee lines that scheme's
 * awards touched this term; a fee line carrying discounts from two
 * different schemes at once (a real but rare case) contributes its
 * gross to both schemes' own figures — each scheme's `discount_minor`
 * stays exact regardless, since it's summed from that scheme's own
 * utilisation rows directly, never derived from the shared gross.
 */
final class GenerateCostOfGenerosityReportAction extends Action
{
    /**
     * @return array<int, CostOfGenerositySchemeSummary>
     */
    public function execute(int $schoolId, int $termId): array
    {
        $utilisation = AwardUtilisation::where('school_id', $schoolId)
            ->where('term_id', $termId)
            ->with(['award.scheme', 'feeLine'])
            ->get();

        return $utilisation
            ->groupBy(fn (AwardUtilisation $u): int => $u->award->scheme_id)
            ->map(function ($rows): CostOfGenerositySchemeSummary {
                $scheme = $rows->first()->award->scheme;
                $grossMinor = $rows->unique('fee_line_id')->sum(fn (AwardUtilisation $u): int => $u->feeLine->gross_minor);
                $discountMinor = (int) $rows->sum('discount_minor');

                return new CostOfGenerositySchemeSummary(
                    schemeId: $scheme->id,
                    schemeCode: $scheme->code,
                    schemeName: $scheme->name,
                    grossMinor: $grossMinor,
                    discountMinor: $discountMinor,
                    netMinor: $grossMinor - $discountMinor,
                    currency: $rows->first()->currency,
                );
            })
            ->values()
            ->all();
    }
}
