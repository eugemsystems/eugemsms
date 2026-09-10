<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Boarding\Domain\DataObjects\BookVisitingDaySlotData;
use Modules\Boarding\Domain\Exceptions\VisitingSlotFullException;
use Modules\Boarding\Models\VisitingDay;
use Modules\Boarding\Models\VisitingDayBooking;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-BookVisitingDaySlot (Book F BRD-03 §4/BR-BRD-03-022). Prevents
 * overcrowding by refusing once a slot's `max_per_slot` party count
 * is reached.
 */
final class BookVisitingDaySlotAction extends Action
{
    public function execute(BookVisitingDaySlotData $data): VisitingDayBooking
    {
        $visitingDay = VisitingDay::findOrFail($data->visitingDayId);

        if ($visitingDay->max_per_slot !== null) {
            $booked = VisitingDayBooking::query()
                ->where('visiting_day_id', $visitingDay->id)
                ->where('slot_starts_at', $data->slotStartsAt)
                ->where('status', 'booked')
                ->sum('party_size');

            if ($booked + $data->partySize > $visitingDay->max_per_slot) {
                throw VisitingSlotFullException::forSlot($visitingDay->id, $data->slotStartsAt);
            }
        }

        return $this->transaction(fn (): VisitingDayBooking => VisitingDayBooking::create([
            'school_id' => $visitingDay->school_id,
            'visiting_day_id' => $visitingDay->id,
            'student_id' => $data->studentId,
            'guardian_id' => $data->guardianId,
            'slot_starts_at' => $data->slotStartsAt,
            'party_size' => $data->partySize,
            'status' => 'booked',
            'booked_at' => Carbon::now(),
        ]));
    }
}
