<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\School;
use Modules\Core\Models\Term;
use Modules\Operations\Domain\Actions\CreateWorkOrderAction;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Models\WorkOrder;
use Modules\Transport\Models\Vehicle;
use Modules\Transport\Models\VehicleCompliance;

/**
 * ACT-RaiseComplianceRenewalWorkOrder (Book H2 OPS-01 §4/BR-OPS-01-003
 * — "a renewal work order can be raised from the alert"). Real
 * `OPS-02` wiring: creates a genuine `WorkOrder` against the vehicle's
 * own `maintenance_asset_id` (when one is linked) and records it back
 * onto the compliance row.
 */
final class RaiseComplianceRenewalWorkOrderAction extends Action
{
    public function __construct(
        private readonly CreateWorkOrderAction $createWorkOrder,
    ) {}

    public function execute(int $complianceId, int $costCentreId, int $raisedByUserId): WorkOrder
    {
        $compliance = VehicleCompliance::findOrFail($complianceId);
        $vehicle = Vehicle::findOrFail($compliance->vehicle_id);
        $term = Term::where('school_id', $vehicle->school_id)->where('is_current', true)->firstOrFail();
        $currency = School::findOrFail($vehicle->school_id)->base_currency;

        return $this->transaction(function () use ($compliance, $vehicle, $term, $currency, $costCentreId, $raisedByUserId): WorkOrder {
            $workOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                schoolId: $vehicle->school_id,
                academicYearId: $term->academic_year_id,
                termId: $term->id,
                workType: 'compliance',
                title: "Renew {$compliance->compliance_type} — {$vehicle->fleet_number}",
                description: "Compliance renewal for {$compliance->compliance_type}, expired/expiring {$compliance->expires_on->toDateString()}.",
                priority: 'high',
                assignedTeam: 'contractor',
                costCentreId: $costCentreId,
                currency: $currency,
                raisedByUserId: $raisedByUserId,
                maintenanceAssetId: $vehicle->maintenance_asset_id,
            ));

            $compliance->update(['renewal_wo_id' => $workOrder->id]);

            return $workOrder;
        });
    }
}
