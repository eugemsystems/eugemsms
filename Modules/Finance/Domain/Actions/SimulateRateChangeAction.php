<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Currency;
use Modules\Core\Models\School;
use Modules\Finance\Domain\DataObjects\RateChangeSimulation;
use Modules\Finance\Domain\DataObjects\SimulateRateChangeData;
use Modules\Finance\Domain\Support\RevaluationCalculator;

/**
 * ACT-SimulateRateChange (Book B FIN-06 §5/BR-FIN-06-013/AC-FIN-06-005).
 * Read-only preview: reuses `RevaluationCalculator` with a proposed
 * rate that is never persisted, so a bursar can see the impact on
 * debtors, creditors, and the FX result before anyone approves anything.
 */
final class SimulateRateChangeAction extends Action
{
    protected bool $transactional = false;

    public function __construct(
        private readonly RevaluationCalculator $calculator,
    ) {}

    public function execute(SimulateRateChangeData $data): RateChangeSimulation
    {
        $school = School::withoutGlobalScopes()->findOrFail($data->schoolId);
        $baseCurrency = Currency::from($school->base_currency);
        $foreignCurrency = Currency::from($data->foreignCurrency);

        $results = $this->calculator->compute($data->schoolId, $baseCurrency, $foreignCurrency, $data->proposedRate, $data->asAt);

        $debtorsRecorded = 0;
        $debtorsRevalued = 0;
        $creditorsRecorded = 0;
        $creditorsRevalued = 0;
        $fxResult = 0;

        foreach ($results as $result) {
            if ($result->isDebitNormal) {
                $debtorsRecorded += $result->recordedBaseMinor;
                $debtorsRevalued += $result->revaluedBaseMinor;
            } else {
                $creditorsRecorded += $result->recordedBaseMinor;
                $creditorsRevalued += $result->revaluedBaseMinor;
            }

            $fxResult += $result->pnlImpactMinor();
        }

        return new RateChangeSimulation(
            proposedRate: $data->proposedRate,
            totalDebtorsRecordedMinor: $debtorsRecorded,
            totalDebtorsRevaluedMinor: $debtorsRevalued,
            totalCreditorsRecordedMinor: $creditorsRecorded,
            totalCreditorsRevaluedMinor: $creditorsRevalued,
            fxResultMinor: $fxResult,
            accounts: $results,
        );
    }
}
