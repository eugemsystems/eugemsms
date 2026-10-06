<?php

declare(strict_types=1);

namespace Modules\People\Domain\Listeners;

use Modules\People\Domain\Actions\RecordStudentTimelineEventAction;
use Modules\People\Domain\DataObjects\RecordStudentTimelineEventData;
use Modules\People\Domain\Events\LearnerEnrolled;
use Modules\People\Domain\Events\LearnerStatusChanged;
use Throwable;

/**
 * Book C PPL-01 §2. Feeds the learner timeline from People's own events. A
 * failure to write a timeline line never blocks the change it describes. A
 * transfer-out writes its own, richer line, so it is skipped here.
 */
final class RecordLearnerTimelineListener
{
    public function __construct(private readonly RecordStudentTimelineEventAction $record) {}

    public function onEnrolled(LearnerEnrolled $event): void
    {
        $this->write($event->student->school_id, $event->student->id, 'enrolled', 'Enrolled', 'info', "Admission number {$event->student->admission_number}");
    }

    public function onStatusChanged(LearnerStatusChanged $event): void
    {
        if ($event->toStatus === 'transferred') {
            return;
        }

        $serious = in_array($event->toStatus, ['suspended', 'withdrawn', 'deceased'], true);

        $this->write($event->student->school_id, $event->student->id, 'status_'.$event->toStatus, 'Status changed to '.str_replace('_', ' ', $event->toStatus), $serious ? 'warning' : 'info', "From {$event->fromStatus}");
    }

    private function write(int $schoolId, int $studentId, string $type, string $title, string $severity, ?string $summary): void
    {
        try {
            $this->record->execute(new RecordStudentTimelineEventData($schoolId, $studentId, 'administrative', $type, $title, $summary, $severity, recordedByUserId: auth()->user()?->id));
        } catch (Throwable) {
            // The timeline is a summary; losing a line must never block the change.
        }
    }
}
