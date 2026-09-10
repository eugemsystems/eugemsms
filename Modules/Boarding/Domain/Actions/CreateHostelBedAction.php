<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateHostelBedData;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateHostelBed (Book F BRD-01 §2/BR-BRD-01-002). Recomputes
 * the owning hostel's capacity in the same transaction — capacity is
 * always in sync with the beds that actually exist.
 */
final class CreateHostelBedAction extends Action
{
    public function __construct(
        private readonly RecalculateHostelCapacityAction $recalculateCapacity,
    ) {}

    public function execute(CreateHostelBedData $data): HostelBed
    {
        $room = HostelRoom::findOrFail($data->roomId);

        return $this->transaction(function () use ($room, $data): HostelBed {
            $bed = HostelBed::create([
                'school_id' => $data->schoolId,
                'room_id' => $data->roomId,
                'bed_number' => $data->bedNumber,
                'bed_type' => $data->bedType,
                'condition_grade' => 'good',
                'is_available' => true,
            ]);

            $this->recalculateCapacity->execute($room->hostel_id);

            return $bed;
        });
    }
}
