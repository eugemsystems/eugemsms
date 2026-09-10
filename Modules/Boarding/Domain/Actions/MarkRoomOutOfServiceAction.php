<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\MarkRoomOutOfServiceData;
use Modules\Boarding\Domain\Events\RoomOutOfService;
use Modules\Boarding\Domain\Exceptions\RoomOccupiedException;
use Modules\Boarding\Models\BedAllocation;
use Modules\Boarding\Models\HostelRoom;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-MarkRoomOutOfService (Book F BRD-01 §4/BR-BRD-01-009/AC-BRD-01-007).
 * A room's occupants must be reallocated before it can be marked out
 * of service — this action refuses outright rather than displacing
 * anyone itself.
 */
final class MarkRoomOutOfServiceAction extends Action
{
    public function __construct(
        private readonly RecalculateHostelCapacityAction $recalculateCapacity,
    ) {}

    public function execute(MarkRoomOutOfServiceData $data): HostelRoom
    {
        $room = HostelRoom::findOrFail($data->roomId);

        $occupantCount = BedAllocation::query()
            ->where('room_id', $room->id)
            ->where('status', 'confirmed')
            ->whereNull('effective_to')
            ->count();

        if ($occupantCount > 0) {
            throw RoomOccupiedException::forRoom($room->id, $occupantCount);
        }

        return $this->transaction(function () use ($room, $data): HostelRoom {
            $room->update(['condition_grade' => 'out_of_service', 'notes' => $data->reason]);

            $this->recalculateCapacity->execute($room->hostel_id);

            event(new RoomOutOfService($room));

            return $room;
        });
    }
}
