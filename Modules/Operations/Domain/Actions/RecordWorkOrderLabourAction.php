<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Operations\Domain\DataObjects\RecordWorkOrderLabourData;
use Modules\Operations\Models\WorkOrder;
use Modules\Operations\Models\WorkOrderLabour;

/**
 * ACT-RecordWorkOrderLabour (Book H2 OPS-02 §3/BR-OPS-02-006). Uses
 * the staff member's own rate where the caller supplies one, else
 * `maintenance.default_trade_hourly_rate_minor`.
 */
final class RecordWorkOrderLabourAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(RecordWorkOrderLabourData $data): WorkOrder
    {
        $workOrder = WorkOrder::findOrFail($data->workOrderId);

        $rate = $data->hourlyRateMinor
            ?? (int) $this->settings->get('maintenance.default_trade_hourly_rate_minor', new ScopeChain(schoolId: $workOrder->school_id));
        $costMinor = (int) round($data->hours * $rate);

        return $this->transaction(function () use ($workOrder, $data, $rate, $costMinor): WorkOrder {
            WorkOrderLabour::create([
                'school_id' => $workOrder->school_id,
                'work_order_id' => $workOrder->id,
                'staff_id' => $data->staffId,
                'work_date' => $data->workDate->toDateString(),
                'hours' => $data->hours,
                'hourly_rate_minor' => $rate,
                'cost_minor' => $costMinor,
                'notes' => $data->notes,
            ]);

            $labourHours = (float) $workOrder->labour_hours + $data->hours;
            $labourCostMinor = $workOrder->labour_cost_minor + $costMinor;

            $workOrder->update([
                'labour_hours' => $labourHours,
                'labour_cost_minor' => $labourCostMinor,
                'total_cost_minor' => $labourCostMinor + $workOrder->parts_cost_minor + $workOrder->contractor_cost_minor,
            ]);

            return $workOrder->refresh();
        });
    }
}
