<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\RecordExamMarksData;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;

/**
 * ACT-RecordExamMarks (Book C PPL-02 §2). Captures one candidate's paper marks
 * and computes their weighted percentage. A mark cannot exceed its paper's
 * maximum; an absentee has no marks. Entering marks starts the exam if it was
 * only scheduled, and a published exam is closed to changes.
 */
final class RecordExamMarksAction extends Action
{
    public function execute(RecordExamMarksData $data): EntranceExamCandidate
    {
        $candidate = EntranceExamCandidate::findOrFail($data->candidateId);
        $exam = EntranceExam::findOrFail($candidate->exam_id);

        if (in_array($exam->status, ['marked', 'published'], true)) {
            throw new InvalidArgumentException('Marks can no longer be changed for this exam.');
        }

        $marks = [];
        $total = null;
        $percentage = null;

        if ($data->attended) {
            $weighted = 0.0;
            $rawTotal = 0.0;

            foreach ($exam->papers as $paper) {
                $subject = (string) $paper['subject'];

                if (! array_key_exists($subject, $data->marks)) {
                    throw new InvalidArgumentException("Enter a mark for {$subject}.");
                }

                $mark = (float) $data->marks[$subject];

                if ($mark < 0 || $mark > (float) $paper['max_mark']) {
                    throw new InvalidArgumentException("{$subject} must be between 0 and {$paper['max_mark']}.");
                }

                $marks[$subject] = $mark;
                $rawTotal += $mark;
                $weighted += $mark / (float) $paper['max_mark'] * (float) $paper['weight'];
            }

            $total = round($rawTotal, 2);
            $percentage = round($weighted, 2);
        }

        return $this->transaction(function () use ($candidate, $exam, $data, $marks, $total, $percentage): EntranceExamCandidate {
            $candidate->update([
                'attended' => $data->attended,
                'marks' => $data->attended ? $marks : null,
                'total_mark' => $total,
                'percentage' => $percentage,
                'rank_in_exam' => null,
            ]);

            if ($exam->status === 'scheduled') {
                $exam->update(['status' => 'in_progress']);
            }

            return $candidate->fresh();
        });
    }
}
