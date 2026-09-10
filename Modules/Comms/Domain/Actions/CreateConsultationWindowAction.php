<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Actions;

use Carbon\CarbonInterface;
use Modules\Comms\Models\ConsultationWindow;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateConsultationWindow (Book I COM-07 §2).
 */
final class CreateConsultationWindowAction extends Action
{
    public function execute(
        int $schoolId,
        int $termId,
        int $staffId,
        string $eventName,
        CarbonInterface $availableFrom,
        CarbonInterface $availableTo,
        int $slotDurationMinutes = 10,
        ?CarbonInterface $bookingOpensAt = null,
        ?CarbonInterface $bookingClosesAt = null,
    ): ConsultationWindow {
        return $this->transaction(fn (): ConsultationWindow => ConsultationWindow::create([
            'school_id' => $schoolId,
            'term_id' => $termId,
            'staff_id' => $staffId,
            'event_name' => $eventName,
            'slot_duration_minutes' => $slotDurationMinutes,
            'available_from' => $availableFrom,
            'available_to' => $availableTo,
            'booking_opens_at' => $bookingOpensAt,
            'booking_closes_at' => $bookingClosesAt,
        ]));
    }
}
