<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Documents;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Documents\GapReportData;
use Modules\Core\Domain\DataObjects\Documents\GapReportEntry;
use Modules\Core\Models\AllocatedNumber;

/**
 * ACT-GenerateGapReport (Book A CORE-06 BR-CORE-06-006) — every voided
 * sequence with its reason, part of the audit pack.
 */
final class GenerateGapReportAction extends Action
{
    /**
     * @return array<int, GapReportEntry>
     */
    public function execute(GapReportData $data): array
    {
        return AllocatedNumber::withoutGlobalScopes()
            ->where('school_id', $data->schoolId)
            ->when($data->seriesId !== null, fn ($query) => $query->where('series_id', $data->seriesId))
            ->where('status', 'voided')
            ->orderBy('sequence')
            ->get()
            ->map(fn (AllocatedNumber $number): GapReportEntry => new GapReportEntry(
                formattedNumber: $number->formatted_number,
                sequence: $number->sequence,
                reason: (string) $number->void_reason,
                voidedAt: $number->voided_at,
            ))
            ->all();
    }
}
