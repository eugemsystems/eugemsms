<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\ReportHostelDamageData;
use Modules\Boarding\Models\HostelDamage;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-ReportHostelDamage (Book F BRD-01 §4/BR-BRD-01-012). Reporting
 * never charges anything by itself — `ApproveHostelDamageChargeAction`
 * is the only path to a `FIN-02` ad hoc charge. `work_order_id` is
 * deliberately left null here — `Modules\Operations`'s
 * `RaiseWorkOrderForHostelDamageAction` (Book H2 OPS-02 §6/
 * BR-OPS-02-014) is the real, explicit link, not an automatic side
 * effect of reporting.
 */
final class ReportHostelDamageAction extends Action
{
    public function execute(ReportHostelDamageData $data): HostelDamage
    {
        return $this->transaction(fn (): HostelDamage => HostelDamage::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'hostel_id' => $data->hostelId,
            'room_id' => $data->roomId,
            'bed_id' => $data->bedId,
            'inspection_id' => $data->inspectionId,
            'damage_type' => $data->damageType,
            'description' => $data->description,
            'photo_file_ids' => $data->photoFileIds,
            'estimated_cost_minor' => $data->estimatedCostMinor,
            'currency' => $data->currency,
            'liability' => $data->liability,
            'liable_student_ids' => $data->liableStudentIds,
            'charge_status' => 'pending',
            'reported_by' => $data->reportedByUserId,
            'reported_at' => $data->reportedAt,
        ]));
    }
}
