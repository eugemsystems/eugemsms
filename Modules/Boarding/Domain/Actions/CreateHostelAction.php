<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateHostelData;
use Modules\Boarding\Models\Hostel;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateHostel (Book F BRD-01 §2).
 */
final class CreateHostelAction extends Action
{
    public function execute(CreateHostelData $data): Hostel
    {
        return $this->transaction(fn (): Hostel => Hostel::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'gender' => $data->gender,
            'section_id' => $data->sectionId,
            'house_id' => $data->houseId,
            'housemaster_staff_id' => $data->housemasterStaffId,
            'matron_staff_id' => $data->matronStaffId,
            'deputy_staff_id' => $data->deputyStaffId,
            'capacity' => 0,
            'building' => $data->building,
            'has_sick_bay' => $data->hasSickBay,
            'has_prep_room' => $data->hasPrepRoom,
            'is_active' => true,
            'created_by' => $data->createdBy,
        ]));
    }
}
