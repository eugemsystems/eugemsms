<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\EntranceExam;

/**
 * ACT-PublishEntranceExamResults (Book C PPL-02 §2). Makes marked results final.
 * A published exam is read only.
 */
final class PublishEntranceExamResultsAction extends Action
{
    public function execute(int $examId): EntranceExam
    {
        $exam = EntranceExam::findOrFail($examId);

        if ($exam->status !== 'marked') {
            throw new InvalidStateTransitionException("Exam #{$exam->id} is [{$exam->status}]; only a marked exam can be published.", ['exam_id' => $exam->id]);
        }

        return $this->transaction(function () use ($exam): EntranceExam {
            $exam->update(['status' => 'published']);

            return $exam->fresh();
        });
    }
}
