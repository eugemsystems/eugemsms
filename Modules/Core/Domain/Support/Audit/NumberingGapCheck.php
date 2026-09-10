<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Audit;

use Modules\Core\Domain\Contracts\Audit\IntegrityCheck;
use Modules\Core\Domain\DataObjects\Audit\IntegrityCheckResult;
use Modules\Core\Models\AllocatedNumber;
use Modules\Core\Models\NumberingSeries;

/**
 * Book A CORE-08 §4/CORE-06 BR-CORE-06-002/006. A true gap (a
 * sequence with no row at all) should be structurally impossible given
 * `AllocateNumberAction`'s row-locked increment — this check exists to
 * catch it anyway (direct database tampering, a bug), and flags the
 * one gap that legitimately happens by design: a voided row with no
 * reason recorded.
 */
final class NumberingGapCheck implements IntegrityCheck
{
    public function checkType(): string
    {
        return 'numbering_gap_scan';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function run(?int $schoolId): IntegrityCheckResult
    {
        $seriesQuery = NumberingSeries::withoutGlobalScopes();

        if ($schoolId !== null) {
            $seriesQuery->where('school_id', $schoolId);
        }

        $failures = [];
        $checked = 0;

        foreach ($seriesQuery->get() as $series) {
            $sequences = AllocatedNumber::withoutGlobalScopes()
                ->where('series_id', $series->id)
                ->orderBy('sequence')
                ->get(['sequence', 'status', 'void_reason']);

            $checked += $sequences->count();
            $expected = 1;

            foreach ($sequences as $number) {
                if ($number->sequence !== $expected) {
                    $failures[] = ['series_id' => $series->id, 'missing_sequence' => $expected, 'reason' => 'sequence never allocated'];
                    $expected = $number->sequence;
                }

                if ($number->status === 'voided' && trim((string) $number->void_reason) === '') {
                    $failures[] = ['series_id' => $series->id, 'sequence' => $number->sequence, 'reason' => 'voided with no reason recorded'];
                }

                $expected++;
            }
        }

        return new IntegrityCheckResult(
            status: $failures === [] ? 'passed' : 'failed',
            recordsChecked: $checked,
            failuresFound: count($failures),
            failureDetails: $failures,
        );
    }
}
