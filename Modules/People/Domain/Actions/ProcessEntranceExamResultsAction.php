<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Application;
use Modules\People\Models\EntranceExam;
use Modules\People\Models\EntranceExamCandidate;

/**
 * ACT-ProcessEntranceExamResults (Book C PPL-02 §2). Closes marking: every
 * candidate must be accounted for (marked or absent), then candidates are ranked
 * by percentage (equal percentages share a rank) and each candidate's
 * application moves to `exam_completed`. The exam becomes `marked`.
 */
final class ProcessEntranceExamResultsAction extends Action
{
    public function execute(int $examId): EntranceExam
    {
        $exam = EntranceExam::findOrFail($examId);

        if ($exam->status !== 'in_progress') {
            throw new InvalidStateTransitionException("Exam #{$exam->id} is [{$exam->status}], not in progress.", ['exam_id' => $exam->id]);
        }

        $candidates = EntranceExamCandidate::query()->where('exam_id', $exam->id)->get();

        if ($candidates->isEmpty() || $candidates->contains(fn (EntranceExamCandidate $c): bool => $c->attended === null)) {
            throw new InvalidArgumentException('Record every candidate as present (with marks) or absent first.');
        }

        return $this->transaction(function () use ($exam, $candidates): EntranceExam {
            $rank = 0;
            $previous = null;
            $position = 0;

            foreach ($candidates->where('attended', true)->sortByDesc(fn (EntranceExamCandidate $c): float => (float) $c->percentage) as $candidate) {
                $position++;
                $rank = $previous !== null && (float) $candidate->percentage === $previous ? $rank : $position;
                $previous = (float) $candidate->percentage;
                $candidate->update(['rank_in_exam' => $rank]);
            }

            Application::query()->whereIn('id', $candidates->pluck('application_id'))->whereIn('status', ['submitted', 'under_review'])->update(['status' => 'exam_completed']);

            $exam->update(['status' => 'marked']);

            return $exam->fresh();
        });
    }
}
