<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\RecordLessonObservationData;
use Modules\Academic\Models\LessonObservation;
use Modules\Academic\Models\ObservationRubric;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\People\Models\Staff;

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
        if ($data->observedStaffId === $data->observerStaffId) {
            throw new InvalidArgumentException('A teacher cannot observe themselves.');
        }

        if (Staff::query()->whereIn('id', [$data->observedStaffId, $data->observerStaffId])->count() !== 2
            || ! Term::query()->whereKey($data->termId)->exists()
            || ($data->subjectId !== null && ! Subject::query()->whereKey($data->subjectId)->exists())) {
            throw new InvalidArgumentException('The staff, term and subject must belong to this school.');
        }

        $rubric = ObservationRubric::query()->find($data->rubricId);

        if ($rubric === null) {
            throw new InvalidArgumentException('Choose one of this school\'s observation rubrics.');
        }

        $levelsByCriterion = [];

        foreach ($rubric->criteria as $criterion) {
            $levelsByCriterion[(string) $criterion['criterion']] = (array) ($criterion['descriptor_levels'] ?? []);
        }

        if ($data->scores === [] || array_diff(array_keys($levelsByCriterion), array_keys($data->scores)) !== []) {
            throw new InvalidArgumentException('Score every criterion on the rubric.');
        }

        foreach ($data->scores as $criterion => $level) {
            if (! isset($levelsByCriterion[(string) $criterion]) || ! in_array($level, $levelsByCriterion[(string) $criterion], true)) {
                throw new InvalidArgumentException("[{$level}] is not a level of the criterion [{$criterion}].");
            }
        }

        if ($data->overallRating !== null && mb_strlen($data->overallRating) > 30) {
            throw new InvalidArgumentException('An overall rating is limited to 30 characters.');
        }

        if ($data->observedAt->isFuture()) {
            throw new InvalidArgumentException('An observation cannot be dated in the future.');
        }

        if ($data->followUpObservationId !== null) {
            $original = LessonObservation::findOrFail($data->followUpObservationId);

            if ($original->observed_staff_id !== $data->observedStaffId) {
                throw new InvalidArgumentException('A follow-up observation must observe the same staff member as the observation it follows.');
            }

            if (! $original->observed_at->lt($data->observedAt)) {
                throw new InvalidArgumentException('A follow-up observation must come after the one it follows.');
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
