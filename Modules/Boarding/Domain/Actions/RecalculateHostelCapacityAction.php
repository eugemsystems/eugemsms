<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Models\Hostel;
use Modules\Boarding\Models\HostelBed;
use Modules\Boarding\Models\HostelRoom;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecalculateHostelCapacity (Book F BRD-01 §4/BR-BRD-01-002).
 * Capacity is derived from active, available beds in rooms that are
 * not out of service — it is never entered manually, and this is the
 * only action that ever writes `hostels.capacity`.
 */
final class RecalculateHostelCapacityAction extends Action
{
    public function execute(int $hostelId): Hostel
    {
        $hostel = Hostel::findOrFail($hostelId);

        $roomIds = HostelRoom::query()
            ->where('hostel_id', $hostelId)
            ->where('condition_grade', '!=', 'out_of_service')
            ->pluck('id');

        $capacity = HostelBed::query()
            ->whereIn('room_id', $roomIds)
            ->where('is_available', true)
            ->count();

        return $this->transaction(fn (): Hostel => tap($hostel)->update(['capacity' => $capacity]));
    }
}
