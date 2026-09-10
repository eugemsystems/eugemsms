<?php

declare(strict_types=1);

namespace Modules\Farm\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Farm\Domain\DataObjects\SavingsReportResult;
use Modules\Farm\Models\InternalTransfer;

/**
 * ACT-ComputeSavingsReport (Book H2 OPS-03 §3/AC-OPS-03-005). Only
 * transfers with a recorded `market_price_minor` count — a transfer
 * nobody priced against the market contributes nothing to either side
 * rather than being silently assumed to match internal cost.
 */
final class ComputeSavingsReportAction extends Action
{
    public function execute(int $schoolId, CarbonInterface $periodStart, CarbonInterface $periodEnd): SavingsReportResult
    {
        $transfers = InternalTransfer::where('school_id', $schoolId)
            ->whereNotNull('market_price_minor')
            ->whereBetween('transfer_date', [$periodStart, $periodEnd])
            ->get(['quantity', 'market_price_minor', 'total_cost_minor']);

        $marketValueMinor = 0;
        $internalCostMinor = 0;

        foreach ($transfers as $transfer) {
            $marketValueMinor += (int) round((float) $transfer->quantity * $transfer->market_price_minor);
            $internalCostMinor += $transfer->total_cost_minor;
        }

        return new SavingsReportResult(
            marketValueMinor: $marketValueMinor,
            internalCostMinor: $internalCostMinor,
            currency: School::findOrFail($schoolId)->base_currency,
        );
    }
}
