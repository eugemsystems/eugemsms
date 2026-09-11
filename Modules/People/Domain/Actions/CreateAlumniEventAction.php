<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Comms\Domain\Actions\CreateCalendarEventAction;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateAlumniEventData;
use Modules\People\Models\AlumniEvent;

/**
 * ACT-CreateAlumniEvent (Book K PPL-06 §4/BR-PPL-06-005). Reuses
 * `COM-06`'s calendar entirely — the underlying `CalendarEvent` is
 * created through Comms' own `CreateCalendarEventAction` first; this
 * module adds only `target_graduation_years` targeting on top.
 */
final class CreateAlumniEventAction extends Action
{
    public function __construct(
        private readonly CreateCalendarEventAction $createCalendarEvent,
    ) {}

    public function execute(CreateAlumniEventData $data): AlumniEvent
    {
        return $this->transaction(function () use ($data): AlumniEvent {
            $calendarEvent = $this->createCalendarEvent->execute(
                schoolId: $data->schoolId,
                academicYearId: $data->academicYearId,
                title: $data->title,
                startsAt: $data->startsAt,
                description: $data->description,
                location: $data->location,
                audienceScope: 'alumni',
            );

            return AlumniEvent::create([
                'school_id' => $data->schoolId,
                'calendar_event_id' => $calendarEvent->id,
                'event_type' => $data->eventType,
                'target_graduation_years' => $data->targetGraduationYears,
                'requires_ticket' => $data->requiresTicket,
            ]);
        });
    }
}
