<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateVisitingDayData;
use Modules\Boarding\Models\VisitingDay;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateVisitingDay (Book F BRD-03 §4/BR-BRD-03-022).
 */
final class CreateVisitingDayAction extends Action
{
    public function execute(CreateVisitingDayData $data): VisitingDay
    {
        return $this->transaction(fn (): VisitingDay => VisitingDay::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'visit_date' => $data->visitDate,
            'name' => $data->name,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'slot_duration_minutes' => $data->slotDurationMinutes,
            'max_per_slot' => $data->maxPerSlot,
            'status' => 'planned',
        ]));
    }
}
