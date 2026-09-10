<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\ModerateExamMarkData;
use Modules\Academic\Models\ExaminationMark;
use Modules\Academic\Models\ExaminationPaper;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ModerateExamMark (Book E ACA-07 §4/BR-ACA-07-014/AC-ACA-07-007).
 * A moderated mark supersedes the settled mark for downstream
 * aggregation, but `raw_mark` (the marker's/third-marker's settled
 * value) is never overwritten — it stays readable alongside
 * `moderated_mark`, mirroring `ModerateProjectAction`'s own reasoning
 * (Book E ACA-06).
 */
final class ModerateExamMarkAction extends Action
{
    public function execute(ModerateExamMarkData $data): ExaminationMark
    {
        $paper = ExaminationPaper::findOrFail($data->paperId);

        $mark = ExaminationMark::query()
            ->where('school_id', $paper->school_id)
            ->where('paper_id', $paper->id)
            ->where('candidate_id', $data->candidateId)
            ->firstOrFail();

        if ($mark->status !== 'final') {
            throw new InvalidStateTransitionException(
                "Candidate #{$data->candidateId}'s mark for paper #{$paper->id} must be final before it can be moderated (currently {$mark->status}).",
                ['mark_id' => $mark->id, 'status' => $mark->status],
            );
        }

        return $this->transaction(fn (): ExaminationMark => tap($mark)->update([
            'moderated_mark' => $data->moderatedMark,
            'moderator_id' => $data->moderatorStaffId,
            'percent' => round(($data->moderatedMark / (float) $paper->max_mark) * 100, 2),
            'status' => 'moderated',
            'version' => $mark->version + 1,
        ]));
    }
}
