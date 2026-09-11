<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\RecordLessonObservationData;
use Modules\Academic\Models\LessonObservation;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordLessonObservation (Book K ACA-11 §4/BR-ACA-11-006). A
 * `followUpObservationId` must observe the SAME staff member as this
 * one — a development trajectory is tracked per teacher, not across
 * teachers.
 */
final class RecordLessonObservationAction extends Action
{
    public function execute(RecordLessonObservationData $data): LessonObservation
    {
        if ($data->followUpObservationId !== null) {
            $original = LessonObservation::findOrFail($data->followUpObservationId);

            if ($original->observed_staff_id !== $data->observedStaffId) {
                throw new InvalidArgumentException('A follow-up observation must observe the same staff member as the observation it follows.');
            }
        }

        return $this->transaction(fn (): LessonObservation => LessonObservation::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'observed_staff_id' => $data->observedStaffId,
            'observer_staff_id' => $data->observerStaffId,
            'rubric_id' => $data->rubricId,
            'observed_at' => $data->observedAt,
            'class_observed' => $data->classObserved,
            'subject_id' => $data->subjectId,
            'scores' => $data->scores,
            'strengths_noted' => $data->strengthsNoted,
            'areas_for_development' => $data->areasForDevelopment,
            'overall_rating' => $data->overallRating,
            'teacher_acknowledged' => false,
            'follow_up_observation_id' => $data->followUpObservationId,
        ]));
    }
}
