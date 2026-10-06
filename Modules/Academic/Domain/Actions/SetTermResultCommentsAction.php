<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\SetTermResultCommentsData;
use Modules\Academic\Models\TermResult;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-SetTermResultComments (Book D ACA-05 §6). Writes the class teacher's and
 * the head's comment onto one learner's term result. A published report is
 * a record: its comments change only through an amendment that regenerates
 * the card, never by editing in place.
 */
final class SetTermResultCommentsAction extends Action
{
    public function execute(SetTermResultCommentsData $data): TermResult
    {
        $result = TermResult::findOrFail($data->termResultId);

        if ($result->status === 'published') {
            throw new InvalidStateTransitionException(
                "Term result #{$result->id} is published; comments can no longer be edited.",
                ['term_result_id' => $result->id, 'status' => $result->status],
            );
        }

        foreach ([$data->classTeacherComment, $data->headComment] as $comment) {
            if ($comment !== null && mb_strlen($comment) > 2000) {
                throw new InvalidArgumentException('A comment is limited to 2000 characters.');
            }
        }

        return $this->transaction(function () use ($result, $data): TermResult {
            $result->update([
                'class_teacher_comment' => $data->classTeacherComment === null ? $result->class_teacher_comment : trim($data->classTeacherComment),
                'head_comment' => $data->headComment === null ? $result->head_comment : trim($data->headComment),
            ]);

            return $result->fresh();
        });
    }
}
