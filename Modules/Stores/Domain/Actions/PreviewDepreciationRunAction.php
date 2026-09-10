<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\PreviewDepreciationRunData;
use Modules\Stores\Domain\Support\DepreciationCalculator;
use Modules\Stores\Models\DepreciationEntry;
use Modules\Stores\Models\DepreciationRun;
use Modules\Stores\Models\FixedAsset;

/**
 * ACT-PreviewDepreciationRun (Book H1 FIN-10 §6/BR-FIN-10-004/005).
 * Purely computational — no ledger posting, no asset mutation. A
 * period that already has a `posted` run is refused up front rather
 * than letting the caller preview something they can never post
 * (the DB's own `UNIQUE(school_id, period_month)` is what actually
 * enforces "never twice", this is just the friendlier early check).
 */
final class PreviewDepreciationRunAction extends Action
{
    public function __construct(
        private readonly DepreciationCalculator $calculator,
    ) {}

    public function execute(PreviewDepreciationRunData $data): DepreciationRun
    {
        $existing = DepreciationRun::where('school_id', $data->schoolId)->where('period_month', $data->periodMonth)->first();

        if ($existing !== null && $existing->status === 'posted') {
            throw ValidationException::withMessages([
                'periodMonth' => "{$data->periodMonth} has already been posted for depreciation (BR-FIN-10-005).",
            ]);
        }

        $periodEnd = Carbon::parse($data->periodMonth.'-01')->endOfMonth();
        // A run for the current, still-in-progress month must not carry a
        // run_date in the future — PostJournalAction refuses that outright.
        $runDate = $periodEnd->greaterThan(Carbon::now()) ? Carbon::now() : $periodEnd;

        $assets = FixedAsset::query()
            ->where('school_id', $data->schoolId)
            ->where('is_depreciable', true)
            ->where('fully_depreciated', false)
            ->where('status', 'active')
            ->whereNotNull('depreciation_start_date')
            ->whereDate('depreciation_start_date', '<=', $periodEnd)
            ->get();

        return $this->transaction(function () use ($data, $existing, $runDate, $assets): DepreciationRun {
            if ($existing !== null) {
                DepreciationEntry::where('run_id', $existing->id)->delete();
                $existing->delete();
            }

            $run = DepreciationRun::create([
                'school_id' => $data->schoolId,
                'academic_year_id' => $data->academicYearId,
                'term_id' => $data->termId,
                'period_month' => $data->periodMonth,
                'run_date' => $runDate->toDateString(),
                'currency' => 'USD',
                'status' => 'preview',
                'computed_by' => $data->computedByUserId,
            ]);

            $assetCount = 0;
            $totalMinor = 0;

            foreach ($assets as $asset) {
                $charge = $this->calculator->monthlyCharge($asset);

                if ($charge <= 0) {
                    continue;
                }

                DepreciationEntry::create([
                    'school_id' => $data->schoolId,
                    'run_id' => $run->id,
                    'asset_id' => $asset->id,
                    'opening_nbv_minor' => $asset->net_book_value_minor,
                    'depreciation_minor' => $charge,
                    'closing_nbv_minor' => $asset->net_book_value_minor - $charge,
                    'method_used' => $asset->depreciation_method,
                ]);

                $assetCount++;
                $totalMinor += $charge;
            }

            $run->update(['asset_count' => $assetCount, 'total_depreciation_minor' => $totalMinor]);

            return $run;
        });
    }
}
