<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateHostelRoomData;
use Modules\Boarding\Models\HostelRoom;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateHostelRoom (Book F BRD-01 §2). Individual beds are added
 * separately via `CreateHostelBedAction` — `bed_count` here is the
 * room's own declared capacity, not a live count.
 */
final class CreateHostelRoomAction extends Action
{
    public function execute(CreateHostelRoomData $data): HostelRoom
    {
        return $this->transaction(fn (): HostelRoom => HostelRoom::create([
            'school_id' => $data->schoolId,
            'hostel_id' => $data->hostelId,
            'wing_id' => $data->wingId,
            'room_number' => $data->roomNumber,
            'room_type' => $data->roomType,
            'bed_count' => $data->bedCount,
            'condition_grade' => 'good',
            'is_ground_floor' => $data->isGroundFloor,
            'proximity_to_exit' => $data->proximityToExit,
            'proximity_to_ablution' => $data->proximityToAblution,
            'has_power_outlet' => true,
            'is_active' => true,
        ]));
    }
}
