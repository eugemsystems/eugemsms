<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\EnterMarkData;
use Modules\Academic\Domain\Exceptions\MarkOutOfRangeException;
use Modules\Academic\Models\Assessment;
use Modules\Academic\Models\AssessmentMark;
use Modules\Academic\Models\GradingScale;
use Modules\Academic\Models\Subject;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-EnterMark (Book D ACA-05 §5/§6/BR-ACA-05-006/007/008). The
 * autosave path: freely overwrites the current `AssessmentMark` while
 * the assessment is `draft`/`open`. Never versions — that only starts
 * once `SubmitAssessmentMarksAction` moves the assessment past this
 * point; see `AmendMarkAction` for every write after that.
 */
final class EnterMarkAction extends Action
{
    public function execute(EnterMarkData $data): AssessmentMark
    {
        $assessment = Assessment::findOrFail($data->assessmentId);

        if (! in_array($assessment->status, ['draft', 'open'], true)) {
            throw new InvalidStateTransitionException(
                'Marks may only be entered while the assessment is draft or open (BR-ACA-05-006) — use AmendMarkAction once submitted.',
                ['assessment_id' => $assessment->id, 'status' => $assessment->status],
            );
        }

        if (! $data->isAbsent && $data->rawMark === null) {
            throw new InvalidArgumentException('A raw mark is required unless the learner is marked absent.');
        }

        $maxMark = (float) $assessment->max_mark;

        if (! $data->isAbsent && ($data->rawMark < 0 || $data->rawMark > $maxMark)) {
            throw MarkOutOfRangeException::forMark($data->rawMark, $maxMark);
        }

        $percent = ! $data->isAbsent ? round(($data->rawMark / $maxMark) * 100, 2) : null;
        $band = $percent !== null ? $this->resolveScale($assessment)?->bandFor($percent) : null;

        return $this->transaction(fn (): AssessmentMark => AssessmentMark::updateOrCreate(
            ['assessment_id' => $assessment->id, 'student_id' => $data->studentId],
            [
                'school_id' => $assessment->school_id,
                'term_id' => $assessment->term_id,
                'raw_mark' => $data->isAbsent ? null : $data->rawMark,
                'percent' => $percent,
                'grade' => $band?->grade,
                'points' => $band?->points,
                'is_absent' => $data->isAbsent,
                'absence_reason' => $data->absenceReason,
                'comment' => $data->comment,
                'version' => 1,
                'entered_by' => $data->enteredByUserId,
                'entered_at' => Carbon::now(),
            ],
        ));
    }

    private function resolveScale(Assessment $assessment): ?GradingScale
    {
        if ($assessment->grading_scale_id !== null) {
            return GradingScale::find($assessment->grading_scale_id);
        }

        $subject = Subject::find($assessment->subject_id);

        return $subject?->grading_scale_id !== null ? GradingScale::find($subject->grading_scale_id) : null;
    }
}
