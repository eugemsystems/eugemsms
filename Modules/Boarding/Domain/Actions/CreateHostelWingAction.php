<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateHostelWingData;
use Modules\Boarding\Models\HostelWing;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateHostelWing (Book F BRD-01 §2). Gap-filling, admin-UI pass:
 * no Action anywhere ever created a `hostel_wings` row before this —
 * every existing one came from `TenantModelRegistry`'s own factory
 * call (verified: `grep -rn "HostelWing::create" Modules/Boarding`
 * returned nothing outside this file). Mirrors
 * `CreateHostelRoomAction`'s own plain-create shape exactly; the
 * `Hostels\Structure` admin screen needs a real path to add a wing
 * between a hostel and its rooms.
 */
final class CreateHostelWingAction extends Action
{
    public function execute(CreateHostelWingData $data): HostelWing
    {
        return $this->transaction(fn (): HostelWing => HostelWing::create([
            'school_id' => $data->schoolId,
            'hostel_id' => $data->hostelId,
            'code' => $data->code,
            'name' => $data->name,
            'floor' => $data->floor,
            'supervisor_staff_id' => $data->supervisorStaffId,
            'prefect_student_id' => $data->prefectStudentId,
            'is_active' => true,
        ]));
    }
}
