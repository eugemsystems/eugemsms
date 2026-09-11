<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book K ACA-11 §4/BR-ACA-11-003. When a lesson plan links to an
 * `ACA-03` timetable slot, its date must be validated against that
 * slot's actual scheduled occurrence rather than freely entered.
 */
class LessonDateNotOnTimetableSlotException extends DomainException
{
    public static function forSlot(int $timetableSlotId, string $lessonDate): self
    {
        return new self(
            "Lesson date [{$lessonDate}] does not fall on timetable slot #{$timetableSlotId}'s own scheduled cycle day.",
            ['timetable_slot_id' => $timetableSlotId, 'lesson_date' => $lessonDate],
        );
    }

    public function errorCode(): string
    {
        return 'LESSON_DATE_NOT_ON_TIMETABLE_SLOT';
    }
}
