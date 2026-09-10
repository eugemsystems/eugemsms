<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\EnterThirdExamMarkData;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-EnterThirdExamMark (Book E ACA-07 §4/BR-ACA-07-013/AC-ACA-07-005).
 * Resolves a mark that `EnterExamMarkAction` routed to
 * `variance_review` — the third marker's own mark settles the
 * candidate directly.
 */
final class EnterThirdExamMarkAction extends Action
{
    public function execute(EnterThirdExamMarkData $data): ExaminationMark
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        $mark = ExaminationMark::query()
            ->where('school_id', $paper->school_id)
            ->where('paper_id', $paper->id)
            ->where('candidate_id', $data->candidateId)
            ->firstOrFail();

        if ($mark->status !== 'variance_review') {
            throw new InvalidStateTransitionException(
                "Candidate #{$data->candidateId}'s mark for paper #{$paper->id} is not awaiting third marking (currently {$mark->status}).",
                ['mark_id' => $mark->id, 'status' => $mark->status],
            );
        }

        $percent = round(($data->mark / (float) $paper->max_mark) * 100, 2);

        return $this->transaction(fn (): ExaminationMark => tap($mark)->update([
            'final_marker_id' => $data->markerStaffId,
            'raw_mark' => $data->mark,
            'percent' => $percent,
            'status' => 'final',
            'version' => $mark->version + 1,
        ]));
    }
}
