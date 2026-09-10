<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Operations\Domain\Actions\CheckUsageBasedMaintenanceAction;
use Modules\Stores\Domain\Actions\ApproveStoreRequisitionAction;
use Modules\Stores\Domain\Actions\IssueStockAction;
use Modules\Stores\Domain\Actions\RequestStoreRequisitionAction;
use Modules\Stores\Domain\DataObjects\RequestStoreRequisitionData;
use Modules\Utilities\Domain\DataObjects\StopGeneratorRunData;
use Modules\Utilities\Domain\Events\GeneratorFuelAnomaly;
use Modules\Utilities\Domain\Events\GeneratorRunRecorded;
use Modules\Utilities\Models\Generator;
use Modules\Utilities\Models\GeneratorRun;

/**
 * ACT-StopGeneratorRun (Book H2 OPS-04 §2 ⭐/BR-OPS-04-011/012/013/
 * AC-OPS-04-007). Diesel drawn from the school tank is a real `FIN-09`
 * requisition against the generator's own cost centre. Hours run
 * advance the generator's real running total and, when it has a
 * linked `OPS-02` `maintenance_asset_id`, trigger the same generic
 * `CheckUsageBasedMaintenanceAction` `Modules\Transport`'s odometer
 * recording already uses — see `GeneratorRunRecorded`'s own docblock
 * for the one piece (`FIN-10`) that stays a documented deferral.
 */
final class StopGeneratorRunAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly RequestStoreRequisitionAction $requestRequisition,
        private readonly ApproveStoreRequisitionAction $approveRequisition,
        private readonly IssueStockAction $issueStock,
        private readonly CheckUsageBasedMaintenanceAction $checkUsageBasedMaintenance,
    ) {}

    public function execute(int $runId, StopGeneratorRunData $data): GeneratorRun
    {
        $run = GeneratorRun::findOrFail($runId);
        $generator = Generator::findOrFail($run->generator_id);

        $hoursRun = round((float) $data->stoppedAt->diffInMinutes($run->started_at, true) / 60, 2);
        $endHourMeter = (float) $run->start_hour_meter + $hoursRun;

        $litresPerHour = null;
        $isAnomaly = false;

        if ($data->dieselLitres !== null && $hoursRun > 0) {
            $litresPerHour = $data->dieselLitres / $hoursRun;

            if ($generator->expected_litres_per_hour !== null && (float) $generator->expected_litres_per_hour > 0) {
                $tolerancePercent = (float) $this->settings->get('utilities.generator_fuel_variance_percent', new ScopeChain(schoolId: $run->school_id));
                $variancePercent = (($litresPerHour - (float) $generator->expected_litres_per_hour) / (float) $generator->expected_litres_per_hour) * 100;
                $isAnomaly = abs($variancePercent) > $tolerancePercent;
            }
        }

        return $this->transaction(function () use ($data, $run, $generator, $hoursRun, $endHourMeter, $litresPerHour, $isAnomaly): GeneratorRun {
            $requisitionId = null;
            $journalId = null;
            $dieselCostMinor = $data->dieselLitres !== null && $data->dieselUnitPriceMinor !== null
                ? (int) round($data->dieselLitres * $data->dieselUnitPriceMinor)
                : null;

            if ($data->dieselLitres !== null && $data->storeId !== null && $data->itemId !== null
                && $data->dieselUnitPriceMinor !== null && $data->currency !== null) {
                $requisition = $this->requestRequisition->execute(new RequestStoreRequisitionData(
                    schoolId: $run->school_id,
                    academicYearId: $data->academicYearId,
                    termId: $run->term_id,
                    storeId: $data->storeId,
                    costCentreId: $generator->cost_centre_id,
                    purpose: "Generator diesel — {$generator->code}",
                    requestedByUserId: (int) ($data->operatedByUserId ?? $run->operated_by),
                    currency: $data->currency,
                    lines: [['itemId' => $data->itemId, 'quantity' => $data->dieselLitres, 'unit' => 'litre']],
                ));
                $this->approveRequisition->execute($requisition->id, (int) ($data->operatedByUserId ?? $run->operated_by));
                $issued = $this->issueStock->execute($requisition->id, (int) ($data->operatedByUserId ?? $run->operated_by));
                $requisitionId = $issued->id;
                $journalId = $issued->journal_id;
            }

            $run->update([
                'stopped_at' => $data->stoppedAt,
                'hours_run' => $hoursRun,
                'end_hour_meter' => $endHourMeter,
                'diesel_litres' => $data->dieselLitres,
                'diesel_cost_minor' => $dieselCostMinor,
                'currency' => $data->currency,
                'litres_per_hour' => $litresPerHour,
                'is_anomaly' => $isAnomaly,
                'store_requisition_id' => $requisitionId,
                'journal_id' => $journalId,
            ]);

            $generator->update(['current_hours' => $endHourMeter, 'status' => 'standby']);

            if ($isAnomaly) {
                event(new GeneratorFuelAnomaly($run));
            }

            event(new GeneratorRunRecorded($run));

            if ($generator->maintenance_asset_id !== null) {
                $this->checkUsageBasedMaintenance->execute($generator->maintenance_asset_id, $endHourMeter, (int) ($data->operatedByUserId ?? $run->operated_by));
            }

            return $run;
        });
    }
}
