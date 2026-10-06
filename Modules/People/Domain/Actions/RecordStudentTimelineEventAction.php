<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RecordStudentTimelineEventData;
use Modules\People\Models\Student;
use Modules\People\Models\StudentTimelineEvent;

/**
 * ACT-RecordStudentTimelineEvent (Book C PPL-01 §2). Adds one line to a
 * learner's timeline. The timeline is an append-only summary that points back to
 * the source record; welfare and safeguarding modules must NOT put detail in
 * `summary` — only that something happened, never what.
 */
final class RecordStudentTimelineEventAction extends Action
{
    public const CATEGORIES = ['academic', 'financial', 'disciplinary', 'health', 'boarding', 'attendance', 'administrative', 'achievement'];

    public function execute(RecordStudentTimelineEventData $data): StudentTimelineEvent
    {
        if (! in_array($data->eventCategory, self::CATEGORIES, true)) {
            throw new InvalidArgumentException("Unknown timeline category [{$data->eventCategory}].");
        }

        if ($data->severity !== null && ! in_array($data->severity, ['info', 'positive', 'warning', 'serious'], true)) {
            throw new InvalidArgumentException("Unknown severity [{$data->severity}].");
        }

        if (trim($data->title) === '' || ! Student::query()->whereKey($data->studentId)->exists()) {
            throw new InvalidArgumentException('A timeline event needs a title and a learner of this school.');
        }

        return $this->transaction(fn (): StudentTimelineEvent => StudentTimelineEvent::create([
            'school_id' => $data->schoolId,
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'event_category' => $data->eventCategory,
            'event_type' => $data->eventType,
            'title' => mb_substr(trim($data->title), 0, 200),
            'summary' => $data->summary === null ? null : mb_substr($data->summary, 0, 500),
            'severity' => $data->severity,
            'source_type' => $data->sourceType,
            'source_id' => $data->sourceId,
            'is_visible_to_guardian' => $data->visibleToGuardian,
            'occurred_at' => ($data->occurredAt ?? Carbon::now())->toDateTimeString(),
            'recorded_by' => $data->recordedByUserId,
        ]));
    }
}
