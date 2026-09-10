<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Domain\DataObjects\CreateWorkOrderData;
use Modules\Operations\Domain\DataObjects\RaiseWorkOrderForHostelDamageData;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-RaiseWorkOrderForHostelDamage (Book H2 OPS-02 §6/BR-OPS-02-014).
 * `hostel_damages.work_order_id` (Book F BRD-01) was deliberately left
 * as a plain forward-reference column, no FK, waiting for this module
 * to exist — this is the real link BR-OPS-02-014 asks for, not a
 * deferral: repair cost (`WorkOrder.total_cost_minor`) and the
 * learner charge (`HostelDamage.ad_hoc_charge_ids`) are both reachable
 * from the same damage record once this runs.
 */
final class RaiseWorkOrderForHostelDamageAction extends Action
{
    public function __construct(
        private readonly CreateWorkOrderAction $createWorkOrder,
    ) {}

    public function execute(RaiseWorkOrderForHostelDamageData $data): WorkOrder
    {
        $damage = HostelDamage::findOrFail($data->hostelDamageId);

        if ($damage->work_order_id !== null) {
            throw new InvalidStateTransitionException(
                "Hostel damage #{$damage->id} already has a work order.",
                ['hostel_damage_id' => $damage->id, 'work_order_id' => $damage->work_order_id],
            );
        }

        return $this->transaction(function () use ($damage, $data): WorkOrder {
            $workOrder = $this->createWorkOrder->execute(new CreateWorkOrderData(
                schoolId: $damage->school_id,
                academicYearId: $data->academicYearId,
                termId: $damage->term_id,
                workType: $data->workType,
                title: "Hostel damage repair — {$damage->damage_type}",
                description: $damage->description,
                priority: $data->priority,
                assignedTeam: $data->assignedTeam,
                costCentreId: $data->costCentreId,
                currency: $damage->currency,
                raisedByUserId: $data->raisedByUserId,
                assignedStaffId: $data->assignedStaffId,
                contractorSupplierId: $data->contractorSupplierId,
                budgetLineId: $data->budgetLineId,
            ), $damage->estimated_cost_minor);

            $damage->update(['work_order_id' => $workOrder->id]);

            return $workOrder;
        });
    }
}
