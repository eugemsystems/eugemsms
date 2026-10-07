<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\SetTermSubjectResultCommentData;
use Modules\Academic\Models\TermResult;
use Modules\Academic\Models\TermSubjectResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SetTermSubjectResultComment (Book D ACA-05 §6). The per-subject
 * counterpart to `SetTermResultCommentsAction`'s term-level class/head
 * comments — writes `term_subject_results.teacher_comment` (and,
 * optionally, who wrote it, into the column the migration already
 * carries but no Action had ever populated). Locked by the same rule
 * as the parent: once the owning `TermResult` is `published`, a
 * subject comment can no longer be edited either, since both flow
 * into the same already-generated report card.
 */
final class SetTermSubjectResultCommentAction extends Action
{
    public function execute(SetTermSubjectResultCommentData $data): TermSubjectResult
    {
        $result = TermSubjectResult::findOrFail($data->termSubjectResultId);

        $termResult = TermResult::query()
            ->where('student_id', $result->student_id)
            ->where('term_id', $result->term_id)
            ->first();

        if ($termResult?->status === 'published') {
            throw new InvalidStateTransitionException(
                "Term result #{$termResult->id} is published; subject comments can no longer be edited.",
                ['term_result_id' => $termResult->id, 'term_subject_result_id' => $result->id],
            );
        }

        if ($data->teacherComment !== null && mb_strlen($data->teacherComment) > 500) {
            throw new InvalidArgumentException('A subject comment is limited to 500 characters.');
        }

        return $this->transaction(function () use ($result, $data): TermSubjectResult {
            $result->update([
                'teacher_comment' => $data->teacherComment === null ? $result->teacher_comment : trim($data->teacherComment),
                'teacher_staff_id' => $data->teacherStaffId ?? $result->teacher_staff_id,
            ]);

            return $result->fresh();
        });
    }
}
