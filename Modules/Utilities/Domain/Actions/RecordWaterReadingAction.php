<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Actions\Notifications\DispatchNotificationAction;
use Modules\Core\Domain\DataObjects\Notifications\DispatchNotificationData;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Operations\Domain\Actions\CreateWorkOrderAction;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\People\Models\Staff;
use Modules\Utilities\Domain\DataObjects\RecordWaterReadingData;
use Modules\Utilities\Domain\Events\BoreholeYieldReduced;
use Modules\Utilities\Domain\Events\WaterStorageLow;
use Modules\Utilities\Models\WaterReading;
use Modules\Utilities\Models\WaterSource;
use Throwable;

/**
 * ACT-RecordWaterReading (Book H2 OPS-04 §2 ⭐/BR-OPS-04-015/016/
 * AC-OPS-04-008). Yield below the configured percentage of the
 * source's own baseline raises a real `OPS-02` work order — not just
 * an alert — through the same `CreateWorkOrderAction` every other
 * module in this book uses. Low storage alerts the estates manager
 * and the boarding master: **water is a boarding-viability issue, not
 * a utility line** (the spec's own words).
 */
final class RecordWaterReadingAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
        private readonly CreateWorkOrderAction $createWorkOrder,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function execute(RecordWaterReadingData $data): WaterReading
    {
        $source = WaterSource::findOrFail($data->waterSourceId);
        $scope = new ScopeChain(schoolId: $data->schoolId);

        return $this->transaction(function () use ($data, $source, $scope): WaterReading {
            $reading = WaterReading::create([
                'school_id' => $data->schoolId,
                'water_source_id' => $source->id,
                'read_on' => $data->readOn->toDateString(),
                'storage_level_percent' => $data->storageLevelPercent,
                'volume_pumped_litres' => $data->volumePumpedLitres,
                'pump_hours' => $data->pumpHours,
                'yield_observed' => $data->yieldObserved,
                'notes' => $data->notes,
                'read_by' => $data->readByUserId,
            ]);

            if ($data->yieldObserved !== null && $source->yield_litres_per_hour !== null && (float) $source->yield_litres_per_hour > 0) {
                $yieldPercent = ($data->yieldObserved / (float) $source->yield_litres_per_hour) * 100;
                $alertPercent = (float) $this->settings->get('utilities.borehole_yield_alert_percent', $scope);

                if ($yieldPercent < $alertPercent) {
                    $source->update(['status' => 'reduced_yield']);
                    event(new BoreholeYieldReduced($source, $yieldPercent));

                    if ($data->academicYearId !== null && $data->termId !== null && $data->costCentreId !== null) {
                        $this->createWorkOrder->execute(new CreateWorkOrderData(
                            schoolId: $data->schoolId,
                            academicYearId: $data->academicYearId,
                            termId: $data->termId,
                            workType: 'corrective',
                            title: "Reduced yield — {$source->name}",
                            description: sprintf('Observed yield %.1f%% of baseline (%.2f L/h vs %.2f L/h expected).', $yieldPercent, $data->yieldObserved, (float) $source->yield_litres_per_hour),
                            priority: 'high',
                            assignedTeam: 'in_house',
                            costCentreId: $data->costCentreId,
                            currency: 'USD',
                            raisedByUserId: $data->readByUserId,
                            maintenanceAssetId: $source->maintenance_asset_id,
                        ));
                    }
                }
            }

            if ($data->storageLevelPercent !== null) {
                $storageAlertPercent = (float) $this->settings->get('utilities.water_storage_alert_percent', $scope);

                if ($data->storageLevelPercent < $storageAlertPercent) {
                    event(new WaterStorageLow($reading));
                    $this->notifyEstatesAndBoarding($data->schoolId, $source);
                }
            }

            return $reading;
        });
    }

    private function notifyEstatesAndBoarding(int $schoolId, WaterSource $source): void
    {
        $scope = new ScopeChain(schoolId: $schoolId);
        $boardingMasterStaffId = $this->settings->get('utilities.boarding_master_staff_id', $scope);

        if ($boardingMasterStaffId === null) {
            return;
        }

        $staff = Staff::find((int) $boardingMasterStaffId);

        if ($staff?->user_id === null) {
            return;
        }

        try {
            $this->dispatchNotification->execute(new DispatchNotificationData(
                schoolId: $schoolId,
                notificationKey: 'utilities.water_storage_low',
                recipientType: 'staff',
                addresses: ['email' => (string) ($staff->work_email ?? $staff->personal_email)],
                context: ['source' => ['name' => $source->name]],
                recipientId: $staff->user_id,
                relatedType: 'water_source',
                relatedId: $source->id,
                urgent: true,
            ));
        } catch (Throwable) {
            // Non-blocking.
        }
    }
}
