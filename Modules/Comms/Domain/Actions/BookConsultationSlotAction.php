<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Modules\Comms\Domain\Exceptions\ConsultationSlotAlreadyBookedException;
use Modules\Comms\Models\ConsultationBooking;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-BookConsultationSlot (Book I COM-07 §3 ⭐/BR-COM-07-005
 * (AC-COM-07-004)). Relies on the DB's own `UNIQUE(window_id,
 * slot_starts_at, status)` index — see the owning migration's
 * docblock — rather than an application-level check-then-write, which
 * can always race between two simultaneous requests. A losing request
 * is offered (in the thrown exception's own context) the next free
 * slot in the same window, computed by scanning existing `booked`
 * rows — never auto-booked into it.
 */
final class BookConsultationSlotAction extends Action
{
    public function execute(int $windowId, int $guardianId, int $studentId, CarbonInterface $slotStartsAt): ConsultationBooking
    {
        $window = ConsultationWindow::findOrFail($windowId);

        try {
            return $this->transaction(fn (): ConsultationBooking => ConsultationBooking::create([
                'school_id' => $window->school_id,
                'window_id' => $windowId,
                'guardian_id' => $guardianId,
                'student_id' => $studentId,
                'slot_starts_at' => $slotStartsAt,
                'status' => 'booked',
            ]));
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            throw ConsultationSlotAlreadyBookedException::forSlot($windowId, $slotStartsAt, $this->nextAvailableSlot($window, $slotStartsAt));
        }
    }

    private function nextAvailableSlot(ConsultationWindow $window, CarbonInterface $after): ?CarbonInterface
    {
        $taken = ConsultationBooking::where('window_id', $window->id)
            ->where('status', 'booked')
            ->pluck('slot_starts_at')
            ->map(fn (CarbonInterface $slot): string => $slot->toIso8601String())
            ->all();

        $candidate = $after->copy()->addMinutes($window->slot_duration_minutes);

        while ($candidate->lessThan($window->available_to)) {
            if (! in_array($candidate->toIso8601String(), $taken, true)) {
                return $candidate;
            }

            $candidate = $candidate->addMinutes($window->slot_duration_minutes);
        }

        return null;
    }
}
