<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\AddObservationTeacherCommentData;
use Modules\Academic\Models\LessonObservation;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\DomainException;

/**
 * ACT-AddObservationTeacherComment (Book K ACA-11 §4/BR-ACA-11-005/
 * AC-ACA-11-003). The observed teacher's ONLY write path onto their
 * own observation record — `scores`, `overall_rating`, and every
 * other observer-authored field are never touched here, by
 * construction (this Action doesn't even accept them as input).
 */
final class AddObservationTeacherCommentAction extends Action
{
    public function execute(AddObservationTeacherCommentData $data): LessonObservation
    {
        $observation = LessonObservation::findOrFail($data->observationId);

        if ($observation->observed_staff_id !== $data->commentingStaffId) {
            throw new class('Only the observed teacher may comment on their own observation record.') extends DomainException
            {
                public function errorCode(): string
                {
                    return 'NOT_THE_OBSERVED_TEACHER';
                }
            };
        }

        return $this->transaction(function () use ($observation, $data): LessonObservation {
            $observation->update([
                'teacher_comments' => $data->comments,
                'teacher_acknowledged' => $data->acknowledged,
            ]);

            return $observation->fresh();
        });
    }
}
